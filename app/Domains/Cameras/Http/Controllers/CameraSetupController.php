<?php

namespace App\Domains\Cameras\Http\Controllers;

use App\Domains\Cameras\Models\Camera;
use App\Domains\Cameras\Models\Dvr;
use App\Domains\Cameras\Services\DahuaClient;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Where the recorder is set up (address and login) and its channels are named and switched on or off. */
class CameraSetupController extends Controller
{
    public function index(): View
    {
        return view('cameras::setup', ['dvrs' => Dvr::with('cameras')->orderBy('name')->get()]);
    }

    public function storeDvr(Request $request, DahuaClient $client): RedirectResponse
    {
        $data = $this->validatedDvr($request, passwordRequired: true);
        $dvr = Dvr::create($data + ['brand' => 'DAHUA', 'active' => true]);

        return redirect()->route('cameras.setup')->with('success', "{$dvr->name} added. Test the connection, then add its channels.");
    }

    public function updateDvr(Request $request, Dvr $dvr): RedirectResponse
    {
        $data = $this->validatedDvr($request, passwordRequired: false);
        if (blank($data['password'] ?? null)) {
            unset($data['password']);   // left blank: the saved password stays
        }
        $dvr->update($data + ['active' => $request->boolean('active')]);

        return redirect()->route('cameras.setup')->with('success', "{$dvr->name} saved.");
    }

    public function destroyDvr(Dvr $dvr): RedirectResponse
    {
        $name = $dvr->name;
        $dvr->delete();

        return redirect()->route('cameras.setup')->with('success', "{$name} and its cameras removed.");
    }

    public function testDvr(Dvr $dvr, DahuaClient $client): RedirectResponse
    {
        $result = $client->test($dvr, (int) ($dvr->cameras()->value('channel') ?? 1));

        return redirect()->route('cameras.setup')->with($result['ok'] ? 'success' : 'warning', $result['message']);
    }

    /** Adds a camera for each channel number from and to, skipping the ones that already exist. */
    public function storeChannels(Request $request, Dvr $dvr): RedirectResponse
    {
        $data = $request->validate(['from' => ['required', 'integer', 'min:1', 'max:128'], 'to' => ['required', 'integer', 'gte:from', 'max:128']],
            ['to.gte' => 'The last channel cannot be below the first.']);
        $existing = $dvr->cameras()->pluck('channel')->all();
        $added = 0;
        foreach (range((int) $data['from'], (int) $data['to']) as $channel) {
            if (in_array($channel, $existing, true)) {
                continue;
            }
            $dvr->cameras()->create(['channel' => $channel, 'name' => 'Camera '.$channel, 'sort_order' => $channel, 'active' => true]);
            $added++;
        }

        return redirect()->route('cameras.setup')->with($added ? 'success' : 'warning', $added ? "{$added} ".($added === 1 ? 'camera' : 'cameras').' added. Give them names and areas below.' : 'Those channels already exist.');
    }

    public function updateCamera(Request $request, Camera $camera): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'area' => ['nullable', 'string', 'max:100'], 'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999']]);
        $camera->update(['name' => trim($data['name']), 'area' => filled($data['area'] ?? null) ? trim($data['area']) : null, 'sort_order' => (int) ($data['sort_order'] ?? $camera->sort_order), 'active' => $request->boolean('active')]);

        return redirect()->route('cameras.setup')->with('success', "{$camera->name} saved.");
    }

    public function destroyCamera(Camera $camera): RedirectResponse
    {
        $name = $camera->name;
        $camera->delete();

        return redirect()->route('cameras.setup')->with('success', "{$name} removed.");
    }

    /** @return array<string, mixed> */
    private function validatedDvr(Request $request, bool $passwordRequired): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'protocol' => ['required', Rule::in(['http', 'https'])],
            // An address only: a name or an IP, with no scheme, path or port, so it cannot point the app anywhere unintended.
            'host' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9]([A-Za-z0-9.\-]*[A-Za-z0-9])?$/'],
            'port' => ['required', 'integer', 'between:1,65535'],
            'username' => ['required', 'string', 'max:100'],
            'password' => [$passwordRequired ? 'required' : 'nullable', 'string', 'max:200'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], ['host.regex' => 'Enter only the recorder\'s address, such as 192.168.1.108, with no http:// and no port.']);
    }
}
