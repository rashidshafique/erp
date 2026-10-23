<?php

namespace App\Services\Codes;

use App\Domains\Core\ModuleRegistry;
use App\Models\AppSetting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Item codes (PRD-00012) and engraving marks (00417). Unique by construction: a counter that never repeats,
 * and a register that refuses duplicates. Style and length come from Settings → Codes and marking.
 */
class CodeGenerator
{
    public const STYLES = ['BOTH' => 'Letters and numbers', 'NUMBERS' => 'Numbers only', 'LETTERS' => 'Letters only'];

    /** kind => [table, default prefix, leading digit used in numbers-only codes, label] */
    private const CORE_KINDS = [
        'product' => ['products', 'PRD', 1, 'Product'],
        'pattern' => ['patterns', 'PAT', 2, 'Pattern'],
        'core_box' => ['core_boxes', 'CBX', 3, 'Core box'],
        'core_definition' => ['core_definitions', 'COR', 4, 'Core'],
        'mould_configuration' => ['mould_configurations', 'MCF', 5, 'Mould configuration'],
        'flask' => ['flasks', 'FLK', 6, 'Flask'],
        'customer' => ['customers', 'C', 7, 'Customer'],
        'material' => ['materials', 'MAT', 8, 'Material'],
        'casting_analysis' => ['casting_analyses', 'CAN', 6, 'Casting analysis'],
    ];

    /**
     * Every kind of coded record: the core ones plus those modules add.
     *
     * @return array<string, array{0: string, 1: string, 2: int, 3: string}>
     */
    public static function kinds(): array
    {
        return self::CORE_KINDS + app(ModuleRegistry::class)->codeKinds();
    }

    public const TOOL_KINDS = ['pattern', 'core_box', 'core_definition', 'mould_configuration', 'flask'];

    public static function setting(string $key, string $default): string
    {
        return AppSetting::getValue('codes.'.$key, $default) ?? $default;
    }

    public static function prefix(string $kind): string
    {
        $p = strtoupper(preg_replace('/[^A-Za-z]/', '', self::setting("prefix.$kind", self::kinds()[$kind][1])));

        return $p !== '' ? substr($p, 0, 5) : self::kinds()[$kind][1];
    }

    public static function codeStyle(): string
    {
        $s = self::setting('code_style', 'BOTH');

        return isset(self::STYLES[$s]) ? $s : 'BOTH';
    }

    public static function markStyle(): string
    {
        $s = self::setting('mark_style', 'NUMBERS');

        return isset(self::STYLES[$s]) ? $s : 'NUMBERS';
    }

    public static function markLength(): int
    {
        return max(3, min(10, (int) self::setting('mark_length', '5')));
    }

    public static function codeDigits(): int
    {
        return max(3, min(8, (int) self::setting('code_digits', '5')));
    }

    /** Next free sequence number for a counter. Atomic, so two people saving at once never get the same one. */
    public function next(string $key): int
    {
        $row = DB::selectOne(
            'INSERT INTO code_sequences (key, next_value) VALUES (?, 2)
             ON CONFLICT (key) DO UPDATE SET next_value = code_sequences.next_value + 1
             RETURNING next_value - 1 AS issued',
            [$key]
        );

        return (int) $row->issued;
    }

    public function code(string $kind): string
    {
        [$table, , $typeDigit] = self::kinds()[$kind];
        do {
            $n = $this->next("code:$kind");
            $code = $this->format($kind, $n, $typeDigit);
        } while (DB::table($table)->where('code', $code)->exists());

        return $code;
    }

    public function format(string $kind, int $n, ?int $typeDigit = null): string
    {
        $typeDigit ??= self::kinds()[$kind][2];
        $digits = self::codeDigits();

        return match (self::codeStyle()) {
            'NUMBERS' => $typeDigit.str_pad((string) $n, $digits, '0', STR_PAD_LEFT),
            'LETTERS' => self::prefix($kind).$this->toBase($n, 'ABCDEFGHJKLMNPRSTUVWXYZ', $digits),
            default => self::prefix($kind).'-'.str_pad((string) $n, $digits, '0', STR_PAD_LEFT),
        };
    }

    /** Characters allowed in an engraving mark. Look-alikes (0/O, 1/I, Q) are left out of letters when asked. */
    public static function markAlphabet(): string
    {
        $avoid = self::setting('mark_avoid_lookalikes', '1') === '1';

        return match (self::markStyle()) {
            'NUMBERS' => '0123456789',
            'LETTERS' => $avoid ? 'ABCDEFGHJKLMNPRSTUVWXYZ' : 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
            default => $avoid ? '23456789ABCDEFGHJKLMNPRSTUVWXYZ' : '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ',
        };
    }

    /** A new, never used engraving mark. */
    public function mark(): string
    {
        $alphabet = self::markAlphabet();
        do {
            $mark = $this->toBase($this->next('mark'), $alphabet, self::markLength());
        } while (DB::table('tool_marks')->where('mark_code', $mark)->exists());

        return $mark;
    }

    /** Give a tool a mark if it has none. Returns the mark. */
    public function ensureMark(string $kind, string $table, string $id): string
    {
        return DB::transaction(function () use ($kind, $table, $id) {
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ["mark:$kind:$id"]);
            $existing = DB::table($table)->where('id', $id)->value('mark_code');
            if ($existing) {
                return $existing;
            }
            $mark = $this->mark();
            $this->register($kind, $id, $mark);
            DB::table($table)->where('id', $id)->update(['mark_code' => $mark]);

            return $mark;
        });
    }

    /** Rules for a mark typed in by hand: what is already engraved on the tool. */
    public const MARK_RULES = ['required', 'string', 'min:3', 'max:20', 'regex:/^[A-Za-z0-9]+$/'];

    /**
     * Records the mark engraved on a tool, such as one made before the app was used. Marks are unique across all tools.
     * The tool's own record is updated (so the change is in its audit history) and the register follows.
     */
    public function changeMark(string $kind, Model $tool, string $mark): void
    {
        $mark = strtoupper(trim($mark));
        if ($mark === $tool->mark_code) {
            return;
        }
        $clash = DB::table('tool_marks')->where('mark_code', $mark)
            ->where(fn ($q) => $q->where('tool_type', '!=', $kind)->orWhere('tool_id', '!=', (string) $tool->getKey()))->exists();
        if ($clash) {
            throw ValidationException::withMessages(['mark_code' => "Mark {$mark} is already on another tool."]);
        }
        DB::transaction(function () use ($kind, $tool, $mark) {
            $tool->update(['mark_code' => $mark]);
            $this->register($kind, (string) $tool->getKey(), $mark);
        });
    }

    public function register(string $kind, string $id, string $mark): void
    {
        DB::table('tool_marks')->updateOrInsert(
            ['tool_type' => $kind, 'tool_id' => $id],
            ['mark_code' => $mark, 'created_at' => now(), 'updated_at' => now()]
        );
    }

    /** Counter value as fixed-width text in the given alphabet; grows by a character if the width runs out. */
    public function toBase(int $n, string $alphabet, int $width): string
    {
        $base = strlen($alphabet);
        $s = '';
        while ($n > 0) {
            $s = $alphabet[$n % $base].$s;
            $n = intdiv($n, $base);
        }

        return str_pad($s, $width, $alphabet[0], STR_PAD_LEFT);
    }

    /** What the next code of a kind would look like (does not use it up). */
    public function preview(string $kind): string
    {
        $next = (int) (DB::table('code_sequences')->where('key', "code:$kind")->value('next_value') ?? 1);

        return $this->format($kind, $next);
    }
}
