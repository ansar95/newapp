<?php

namespace App\Actions\Bmhp;

use App\Exceptions\BusinessRuleException;
use App\Models\MasterBmhp;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves the previewed rows in one transaction: either every valid row is imported or none is.
 */
class ImportMasterBmhps
{
    public function __construct(
        private CreateMasterBmhp $createMasterBmhp,
        private UpdateMasterBmhp $updateMasterBmhp,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $rows  rows produced by ParseMasterBmhpImport
     * @return array{created: int, updated: int, skipped: int}
     */
    public function handle(User $actor, array $rows): array
    {
        return DB::transaction(function () use ($actor, $rows): array {
            $result = ['created' => 0, 'updated' => 0, 'skipped' => 0];

            foreach ($rows as $row) {
                if ($row['action'] === 'create') {
                    $this->create($actor, $row);
                    $result['created']++;
                } elseif ($row['action'] === 'update') {
                    $this->update($actor, $row);
                    $result['updated']++;
                } else {
                    $result['skipped']++;
                }
            }

            return $result;
        });
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function create(User $actor, array $row): void
    {
        $this->guard($row, fn () => $this->createMasterBmhp->handle($actor, [
            'code' => $row['code'],
            'name' => $row['name'],
            'category' => $row['category'],
            'unit' => $row['unit'],
            'description' => $row['description'],
            'status' => $row['status'],
        ]));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function update(User $actor, array $row): void
    {
        $master = MasterBmhp::query()->whereKey($row['existing_id'])->lockForUpdate()->first();

        if ($master === null) {
            throw BusinessRuleException::make('Baris '.$row['line'].': BMHP '.$row['code'].' tidak lagi tersedia. Upload ulang file import.');
        }

        $this->guard($row, fn () => $this->updateMasterBmhp->handle($actor, $master, [
            'code' => $master->code,
            'name' => $row['name'],
            'category' => $row['category'],
            'unit' => $row['unit'],
            'description' => $row['description'],
            'status' => $row['status'] ?? $master->status->value,
        ]));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function guard(array $row, callable $callback): void
    {
        try {
            $callback();
        } catch (ValidationException $exception) {
            throw BusinessRuleException::make(
                'Baris '.$row['line'].': '.collect($exception->errors())->flatten()->first().' Data berubah sejak preview, upload ulang file import.'
            );
        }
    }
}
