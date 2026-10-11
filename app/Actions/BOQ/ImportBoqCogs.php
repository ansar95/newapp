<?php

namespace App\Actions\BOQ;

use App\Enums\BoqStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Boq;
use App\Models\BoqCogs;
use App\Models\User;
use App\Support\BoqCogsCsv;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ImportBoqCogs
{
    /**
     * @return array{cogs_created: int, items_created: int, items_skipped: int}
     */
    public function handle(User $actor, Boq $boq, UploadedFile $file): array
    {
        if ($boq->status !== BoqStatus::Draft) {
            throw BusinessRuleException::make(
                'Import COGS hanya tersedia ketika BOQ berstatus DRAFT.'
            );
        }

        Validator::make(
            ['file' => $file],
            [
                'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
            ],
            [
                'file.mimes' => 'File harus berformat CSV.',
                'file.max' => 'Ukuran file maksimal 2MB.',
            ]
        )->validate();

        try {
            $rows = BoqCogsCsv::parse($file);
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'file' => $exception->getMessage(),
            ]);
        }

        return DB::transaction(function () use ($actor, $boq, $rows): array {
            $boq->loadMissing('cogs.items');

            $cogsCreated = 0;
            $itemsCreated = 0;
            $itemsSkipped = 0;
            /** @var array<string, BoqCogs> $cogsByName */
            $cogsByName = $boq->cogs
                ->keyBy(fn (BoqCogs $cogs) => mb_strtolower($cogs->name))
                ->all();

            foreach ($rows as $row) {
                $line = (int) ($row['_line'] ?? 0);

                $validated = Validator::make($row, [
                    'cogs_name' => ['required', 'string', 'max:255'],
                    'cogs_description' => ['nullable', 'string'],
                    'item_code' => ['required', 'string', 'max:50'],
                    'item_name' => ['required', 'string', 'max:255'],
                    'item_description' => ['nullable', 'string'],
                    'quantity' => ['required', 'numeric', 'min:0'],
                    'unit' => ['required', 'string', 'max:50'],
                    'material_price' => ['required', 'numeric', 'min:0'],
                    'service_price' => ['nullable', 'numeric', 'min:0'],
                    'item_notes' => ['nullable', 'string', 'max:255'],
                ], [
                    'required' => 'Baris '.$line.': kolom :attribute wajib diisi.',
                    'numeric' => 'Baris '.$line.': kolom :attribute harus berupa angka.',
                    'min' => 'Baris '.$line.': kolom :attribute tidak valid.',
                    'item_notes.max' => 'Baris '.$line.': kolom item_notes maksimal 255 karakter.',
                ])->validate();

                $nameKey = mb_strtolower($validated['cogs_name']);

                if (! isset($cogsByName[$nameKey])) {
                    $cogs = app(CreateCogs::class)->handle($actor, $boq->fresh('cogs'), [
                        'name' => $validated['cogs_name'],
                        'description' => $validated['cogs_description'] ?? null,
                    ]);
                    $cogsByName[$nameKey] = $cogs->load('items');
                    $cogsCreated++;
                }

                $cogs = $cogsByName[$nameKey];

                if ($cogs->items->contains(fn ($item) => $item->code === $validated['item_code'])) {
                    $itemsSkipped++;

                    continue;
                }

                $item = app(CreateBoqItem::class)->handle($actor, $cogs, [
                    'code' => $validated['item_code'],
                    'name' => $validated['item_name'],
                    'description' => $validated['item_description'] ?? null,
                    'quantity' => $validated['quantity'],
                    'unit' => $validated['unit'],
                    'material_unit_price' => $validated['material_price'],
                    'service_unit_price' => filled($validated['service_price'] ?? null) ? $validated['service_price'] : 0,
                    'notes' => $validated['item_notes'] ?? null,
                ]);

                $cogs->setRelation('items', $cogs->items->push($item));
                $itemsCreated++;
            }

            return [
                'cogs_created' => $cogsCreated,
                'items_created' => $itemsCreated,
                'items_skipped' => $itemsSkipped,
            ];
        });
    }
}
