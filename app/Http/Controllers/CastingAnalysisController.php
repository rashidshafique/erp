<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\CastingAnalysis;
use App\Models\IronGrade;
use App\Models\Product;
use App\Services\Casting\CastingAnalysisException;
use App\Services\Casting\CastingAnalysisService;
use App\Services\Casting\MeshLoader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CastingAnalysisController extends Controller
{
    public function index(Request $request)
    {
        $rows = CastingAnalysis::query()->with('product:id,code,name')
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($w) => $w->where('code', 'ilike', '%'.trim($request->input('search')).'%')->orWhere('name', 'ilike', '%'.trim($request->input('search')).'%')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->orderByDesc('created_at')->paginate(20)->withQueryString();

        return view('casting-analyses.index', ['rows' => $rows]);
    }

    public function create(Request $request)
    {
        $item = $request->filled('enquiry_item') ? \App\Models\EnquiryItem::with('enquiry')->find($request->input('enquiry_item')) : null;
        $product = $request->filled('product') ? Product::find($request->input('product')) : ($item?->product_id ? Product::find($item->product_id) : null);
        $cad = $product ? Attachment::where('attachable_type', 'product')->where('attachable_id', $product->id)->whereNull('removed_at')
            ->get()->filter(fn ($a) => in_array(strtolower(pathinfo($a->original_name, PATHINFO_EXTENSION)), ['stl', 'stp', 'step'], true))->values() : collect();

        return view('casting-analyses.create', [
            'product' => $product, 'cad' => $cad, 'item' => $item,
            'products' => Product::orderBy('code')->get(['id', 'code', 'name']),
            'grades' => IronGrade::where('status', 'ACTIVE')->whereIn('family', ['GREY', 'DUCTILE'])->orderBy('code')->get(['id', 'code', 'name', 'family']),
        ]);
    }

    public function store(Request $request, CastingAnalysisService $service)
    {
        $max = (int) config('casting.max_upload_kb');
        $d = $request->validate([
            'name' => ['nullable', 'string', 'max:200'],
            'enquiry_item_id' => ['nullable', 'exists:enquiry_items,id'],
            'product_id' => ['nullable', 'exists:products,id'],
            'attachment_id' => ['nullable', 'string'],
            'cad' => ['nullable', 'file', 'max:'.$max, 'extensions:stl,stp,step'],
            'units' => ['required', Rule::in(array_keys(config('casting.units_to_mm')))],
            'quality' => ['required', Rule::in(array_keys(config('casting.resolution')))],
            'family' => ['required', Rule::in(['AUTO', 'GREY', 'DUCTILE'])],
            'iron_grade_id' => ['nullable', 'exists:iron_grades,id'],
            'min_tensile' => ['nullable', 'numeric', 'min:0', 'max:2000'],
            'min_elongation' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'pour_window_min' => ['nullable', 'numeric', 'min:1', 'max:60'],
            'feeder' => ['required', Rule::in(['AUTO', 'CONVENTIONAL', 'INSULATED', 'EXOTHERMIC'])],
            'b_override' => ['nullable', 'numeric', 'min:0.3', 'max:6'],
        ], ['cad.max' => 'The file may be at most '.round($max / 1024).' MB.', 'cad.extensions' => 'Upload an .stl, .stp or .step file.']);

        $disk = config('foundry.attachments.disk');
        $id = (string) Str::uuid();
        $att = null;
        if ($request->hasFile('cad')) {
            $file = $request->file('cad');
            $orig = Str::limit(preg_replace('/[^\pL\pN\s._()-]+/u', '_', basename($file->getClientOriginalName())), 150, '');
            try {
                $type = MeshLoader::typeOf($file->getRealPath(), $orig);
            } catch (CastingAnalysisException $e) {
                return back()->withErrors(['cad' => $e->getMessage()])->withInput();
            }
            $path = $file->storeAs('casting-analyses', $id.'.'.($type === 'STL' ? 'stl' : 'step'), $disk);
            $sha = hash_file('sha256', $file->getRealPath());
            $size = $file->getSize();
        } elseif (! empty($d['attachment_id'])) {
            $att = Attachment::whereNull('removed_at')->where('attachable_type', 'product')->find($d['attachment_id']);
            if (! $att || ! Storage::disk($att->disk)->exists($att->path)) {
                return back()->withErrors(['attachment_id' => 'That attached file is not available.'])->withInput();
            }
            $orig = $att->original_name;
            $tmp = tempnam(sys_get_temp_dir(), 'att');
            file_put_contents($tmp, Storage::disk($att->disk)->get($att->path));
            try {
                $type = MeshLoader::typeOf($tmp, $orig);
            } catch (CastingAnalysisException $e) {
                @unlink($tmp);

                return back()->withErrors(['attachment_id' => $e->getMessage()])->withInput();
            }
            @unlink($tmp);
            $path = 'casting-analyses/'.$id.'.'.($type === 'STL' ? 'stl' : 'step');
            Storage::disk($disk)->put($path, Storage::disk($att->disk)->get($att->path));
            $sha = $att->sha256;
            $size = $att->size_bytes;
            $d['product_id'] ??= $att->attachable_id;
        } else {
            return back()->withErrors(['cad' => 'Choose a CAD file (.stl, .stp or .step) to analyse.'])->withInput();
        }

        $analysis = new CastingAnalysis([
            'name' => ($d['name'] ?? null) ?: pathinfo($orig, PATHINFO_FILENAME),
            'product_id' => $d['product_id'] ?? null, 'iron_grade_id' => $d['iron_grade_id'] ?? null,
            'status' => 'QUEUED', 'source_type' => $type, 'original_name' => $orig,
            'file_disk' => $disk, 'file_path' => $path, 'file_sha256' => $sha, 'file_size' => $size,
            'units' => $d['units'], 'quality' => $d['quality'], 'family_requested' => $d['family'],
            'options' => array_filter([
                'min_tensile' => $d['min_tensile'] ?? null, 'min_elongation' => $d['min_elongation'] ?? null,
                'pour_window_min' => $d['pour_window_min'] ?? null, 'feeder' => $d['feeder'], 'b_override' => $d['b_override'] ?? null,
            ], fn ($v) => $v !== null),
            'created_by_id' => Auth::id(), 'created_by_name' => Auth::user()?->name,
        ]);
        $analysis->id = $id;
        $analysis->save();
        $service->run($analysis);

        if (! empty($d['enquiry_item_id']) && $analysis->status === 'COMPLETE') {
            $item = \App\Models\EnquiryItem::with('enquiry')->find($d['enquiry_item_id']);
            $item->update(['casting_analysis_id' => $analysis->id]);

            return redirect()->route('enquiries.show', $item->enquiry)->with('success', "Analysis {$analysis->code} complete and linked to part {$item->line_no}. Score {$analysis->score}.");
        }

        return redirect()->route('casting-analyses.show', $analysis)->with($analysis->status === 'COMPLETE' ? 'success' : 'error', $analysis->status === 'COMPLETE' ? 'Analysis complete.' : 'The analysis could not be completed: '.$analysis->error);
    }

    public function show(CastingAnalysis $analysis)
    {
        $analysis->load('product:id,code,name', 'grade:id,code,name');

        return view('casting-analyses.show', ['a' => $analysis]);
    }

    /** Same file and settings, different choices (quality, material, feeders). */
    public function rerun(Request $request, CastingAnalysis $analysis, CastingAnalysisService $service)
    {
        $d = $request->validate([
            'units' => ['required', Rule::in(array_keys(config('casting.units_to_mm')))],
            'quality' => ['required', Rule::in(array_keys(config('casting.resolution')))],
            'family' => ['required', Rule::in(['AUTO', 'GREY', 'DUCTILE'])],
            'iron_grade_id' => ['nullable', 'exists:iron_grades,id'],
            'min_tensile' => ['nullable', 'numeric', 'min:0', 'max:2000'],
            'min_elongation' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'pour_window_min' => ['nullable', 'numeric', 'min:1', 'max:60'],
            'feeder' => ['required', Rule::in(['AUTO', 'CONVENTIONAL', 'INSULATED', 'EXOTHERMIC'])],
            'b_override' => ['nullable', 'numeric', 'min:0.3', 'max:6'],
        ]);
        $analysis->fill([
            'units' => $d['units'], 'quality' => $d['quality'], 'family_requested' => $d['family'], 'iron_grade_id' => $d['iron_grade_id'] ?? null,
            'options' => array_filter(['min_tensile' => $d['min_tensile'] ?? null, 'min_elongation' => $d['min_elongation'] ?? null, 'pour_window_min' => $d['pour_window_min'] ?? null, 'feeder' => $d['feeder'], 'b_override' => $d['b_override'] ?? null], fn ($v) => $v !== null),
            'status' => 'QUEUED',
        ])->save();
        $service->run($analysis);

        return redirect()->route('casting-analyses.show', $analysis)->with($analysis->status === 'COMPLETE' ? 'success' : 'error', $analysis->status === 'COMPLETE' ? 'Analysis re-run.' : 'The analysis could not be completed: '.$analysis->error);
    }

    /** The modulus grid for the slice viewer, gzip-compressed bytes. */
    public function field(CastingAnalysis $analysis)
    {
        abort_unless($analysis->field_path && Storage::disk($analysis->file_disk)->exists($analysis->field_path), 404);

        return response(Storage::disk($analysis->file_disk)->get($analysis->field_path), 200, [
            'Content-Type' => 'application/octet-stream', 'Content-Encoding' => 'gzip',
            'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=600',
        ]);
    }

    public function destroy(CastingAnalysis $analysis)
    {
        $disk = Storage::disk($analysis->file_disk);
        foreach ([$analysis->file_path, $analysis->field_path] as $p) {
            if ($p) {
                $disk->delete($p);
            }
        }
        $analysis->delete();

        return redirect()->route('casting-analyses.index')->with('success', 'Analysis deleted.');
    }
}
