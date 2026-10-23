<x-app-layout>
    <x-slot name="header">{{ $project->code }} — Man Power</x-slot>

    <x-breadcrumb :items="[
        ['label' => 'Projects', 'url' => route('projects.index')],
        ['label' => $project->code, 'url' => route('projects.show', $project)],
        ['label' => 'Man Power', 'url' => null],
    ]" />

    <x-project-header :project="$project" :tab="$tab" :context="$context" />

    <div class="mb-4 rounded-xl border border-maroon-200 bg-white p-4">
        <p class="text-sm font-medium text-maroon-900">Tenaga kerja project dari Master Man Power.</p>
        <x-helper-text class="mt-1" text="Total gaji = hari kerja × upah harian. Kasbon dan pelunasan dicatat sebagai pengeluaran dan masuk Actual Cost project (tidak dialokasikan ke item BOQ). Total kasbon + pelunasan tidak boleh melebihi total gaji." />
    </div>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-maroon-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Total Gaji</p>
            <p class="mt-2 text-xl font-semibold"><x-money :amount="$totalSalary" /></p>
            <p class="mt-1 text-xs text-maroon-700">{{ $members->count() }} orang</p>
        </div>
        <div class="rounded-xl border border-maroon-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Kasbon</p>
            <p class="mt-2 text-xl font-semibold"><x-money :amount="$totalKasbon" /></p>
        </div>
        <div class="rounded-xl border border-maroon-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Pelunasan</p>
            <p class="mt-2 text-xl font-semibold"><x-money :amount="$totalPelunasan" /></p>
        </div>
        <div class="rounded-xl border border-maroon-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Sisa Belum Dibayar</p>
            <p class="mt-2 text-xl font-semibold"><x-money :amount="$totalRemaining" /></p>
        </div>
    </div>

    @can('assign', [\App\Models\ProjectManPower::class, $project])
        <div class="mb-6 rounded-xl border border-maroon-200 bg-white p-5 shadow-sm"
             x-data="{
                 open: {{ $errors->hasAny(['man_power_id', 'work_days', 'daily_wage', 'position', 'start_date']) && old('man_power_id') ? 'true' : 'false' }},
                 submitting: false,
                 masters: @js($availableManPowers->mapWithKeys(fn ($manPower) => [$manPower->id => ['position' => $manPower->position, 'wage' => (float) $manPower->daily_wage]])),
                 position: @js(old('position', '')),
                 wage: @js(old('daily_wage', '')),
                 pick(id) { const master = this.masters[id]; if (master) { this.position = master.position; this.wage = master.wage; } },
             }">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h3 class="font-semibold text-maroon-950">Tambah Man Power</h3>
                    <x-helper-text text="Posisi dan upah harian terisi dari master, dapat diubah khusus untuk project ini." />
                </div>
                <button type="button"
                        class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-medium text-white hover:bg-maroon-900"
                        @click="open = !open"
                        x-text="open ? 'Tutup' : '+ Tambah Man Power'">
                    + Tambah Man Power
                </button>
            </div>

            <form method="POST" action="{{ route('projects.man-power.store', $project) }}" class="mt-4 space-y-4"
                  x-show="open" x-cloak @submit="submitting = true">
                @csrf
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <x-input-label value="Man Power *" />
                        <select name="man_power_id" class="mt-1 w-full rounded-md border-maroon-300 text-sm" required @change="pick($event.target.value)">
                            <option value="">Pilih man power</option>
                            @foreach ($availableManPowers as $manPower)
                                <option value="{{ $manPower->id }}" @selected((string) old('man_power_id') === (string) $manPower->id)>
                                    {{ $manPower->code }} — {{ $manPower->name }} ({{ $manPower->position }})
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('man_power_id')" class="mt-1" />
                        @if ($availableManPowers->isEmpty())
                            <p class="mt-1 text-xs text-maroon-900">Tidak ada man power aktif yang belum terdaftar. Tambahkan di <a href="{{ route('man-powers.index') }}" class="underline">Master Man Power</a>.</p>
                        @endif
                    </div>
                    <div>
                        <x-input-label value="Posisi" />
                        <input type="text" name="position" x-model="position" class="mt-1 w-full rounded-md border-maroon-300 text-sm">
                        <x-input-error :messages="$errors->get('position')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label value="Upah Harian (Rp)" />
                        <input type="number" name="daily_wage" x-model="wage" min="0" step="0.01" class="mt-1 w-full rounded-md border-maroon-300 text-sm">
                        <x-input-error :messages="$errors->get('daily_wage')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label value="Hari Kerja *" />
                        <input type="number" name="work_days" value="{{ old('work_days', 0) }}" min="0" step="1" class="mt-1 w-full rounded-md border-maroon-300 text-sm" required>
                        <x-input-error :messages="$errors->get('work_days')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label value="Tanggal Mulai" />
                        <x-date-input name="start_date" :value="old('start_date')" class="mt-1 w-full text-sm" />
                        <x-input-error :messages="$errors->get('start_date')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label value="Catatan" />
                        <input type="text" name="notes" value="{{ old('notes') }}" class="mt-1 w-full rounded-md border-maroon-300 text-sm">
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="submit"
                            class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-medium text-white disabled:opacity-60"
                            :disabled="submitting"
                            x-text="submitting ? 'Processing...' : 'Tambahkan'">
                        Tambahkan
                    </button>
                </div>
            </form>
        </div>
    @endcan

    @if ($members->isEmpty())
        <x-empty-state title="Belum ada man power pada project ini." description="Tambahkan man power dari master untuk mencatat hari kerja, gaji, kasbon, dan pelunasan." />
    @else
        <div class="overflow-hidden rounded-xl border border-maroon-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-maroon-200 text-sm">
                    <thead class="bg-maroon-50 text-left text-xs font-semibold uppercase tracking-wide text-maroon-700">
                    <tr>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">Posisi</th>
                        <th class="px-4 py-3 text-right">Hari Kerja</th>
                        <th class="px-4 py-3 text-right">Upah Harian</th>
                        <th class="px-4 py-3 text-right">Total Gaji</th>
                        <th class="px-4 py-3 text-right">Kasbon</th>
                        <th class="px-4 py-3 text-right">Pelunasan</th>
                        <th class="px-4 py-3 text-right">Sisa</th>
                        <th class="px-4 py-3">Action</th>
                    </tr>
                    </thead>
                    @foreach ($members as $member)
                        <tbody class="divide-y divide-maroon-100 border-b border-maroon-200" x-data="{ panel: null }">
                        <tr class="hover:bg-maroon-50">
                            <td class="px-4 py-3">
                                <div class="font-medium text-maroon-950">{{ $member->manPower->name }}</div>
                                <div class="text-xs text-maroon-700">
                                    {{ $member->manPower->code }}
                                    @if ($member->start_date)
                                        · mulai {{ date_id($member->start_date) }}
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3">{{ $member->position }}</td>
                            <td class="px-4 py-3 text-right">{{ $member->work_days }}</td>
                            <td class="px-4 py-3 text-right"><x-money :amount="$member->daily_wage" /></td>
                            <td class="px-4 py-3 text-right font-medium"><x-money :amount="$member->totalSalary()" /></td>
                            <td class="px-4 py-3 text-right"><x-money :amount="$member->paidAmount(\App\Enums\ManPowerTransactionType::Kasbon)" /></td>
                            <td class="px-4 py-3 text-right"><x-money :amount="$member->paidAmount(\App\Enums\ManPowerTransactionType::Pelunasan)" /></td>
                            <td class="px-4 py-3 text-right font-medium"><x-money :amount="$member->remainingSalary()" /></td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-2 whitespace-nowrap">
                                    @can('recordTransaction', $member)
                                        <button type="button" class="text-sm font-medium text-maroon-800 hover:underline" @click="panel = panel === 'pay' ? null : 'pay'">Kasbon / Pelunasan</button>
                                    @endcan
                                    @can('update', $member)
                                        <button type="button" class="text-sm font-medium text-maroon-800 hover:underline" @click="panel = panel === 'edit' ? null : 'edit'">Edit</button>
                                    @endcan
                                    <button type="button" class="text-sm font-medium text-maroon-900 hover:underline" @click="panel = panel === 'history' ? null : 'history'">
                                        Riwayat ({{ $member->transactions->count() }})
                                    </button>
                                </div>
                            </td>
                        </tr>

                        @can('recordTransaction', $member)
                            <tr x-show="panel === 'pay'" x-cloak class="bg-maroon-50/50">
                                <td colspan="9" class="px-4 py-4">
                                    <form method="POST" action="{{ route('man-power-transactions.store', $member) }}"
                                          class="grid gap-3 sm:grid-cols-5 sm:items-end"
                                          onsubmit="return confirm('Catat transaksi ini sebagai pengeluaran project?');">
                                        @csrf
                                        <div>
                                            <x-input-label value="Jenis *" />
                                            <select name="type" class="mt-1 w-full rounded-md border-maroon-300 text-sm" required>
                                                @foreach ($transactionTypes as $type)
                                                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <x-input-label value="Tanggal *" />
                                            <x-date-input name="transaction_date" :value="now()->toDateString()" class="mt-1 w-full text-sm" required />
                                        </div>
                                        <div>
                                            <x-input-label value="Nominal (Rp) *" />
                                            <input type="number" name="amount" min="0.01" step="0.01" max="{{ $member->remainingSalary() }}" class="mt-1 w-full rounded-md border-maroon-300 text-sm" required>
                                            <p class="mt-1 text-xs text-maroon-700">Maks. <x-money :amount="$member->remainingSalary()" /></p>
                                        </div>
                                        <div>
                                            <x-input-label value="Catatan" />
                                            <input type="text" name="notes" class="mt-1 w-full rounded-md border-maroon-300 text-sm">
                                        </div>
                                        <div>
                                            <button type="submit" class="w-full rounded-md bg-maroon-800 px-4 py-2 text-sm font-medium text-white">Simpan</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @endcan

                        @can('update', $member)
                            <tr x-show="panel === 'edit'" x-cloak class="bg-maroon-50/50">
                                <td colspan="9" class="px-4 py-4">
                                    <form method="POST" action="{{ route('project-man-powers.update', $member) }}" class="grid gap-3 sm:grid-cols-6 sm:items-end">
                                        @csrf
                                        @method('PUT')
                                        <div>
                                            <x-input-label value="Posisi *" />
                                            <input type="text" name="position" value="{{ $member->position }}" class="mt-1 w-full rounded-md border-maroon-300 text-sm" required>
                                        </div>
                                        <div>
                                            <x-input-label value="Upah Harian *" />
                                            <input type="number" name="daily_wage" value="{{ $member->daily_wage }}" min="0" step="0.01" class="mt-1 w-full rounded-md border-maroon-300 text-sm" required>
                                        </div>
                                        <div>
                                            <x-input-label value="Hari Kerja *" />
                                            <input type="number" name="work_days" value="{{ $member->work_days }}" min="0" step="1" class="mt-1 w-full rounded-md border-maroon-300 text-sm" required>
                                        </div>
                                        <div>
                                            <x-input-label value="Tanggal Mulai" />
                                            <x-date-input name="start_date" :value="$member->start_date?->toDateString()" class="mt-1 w-full text-sm" />
                                        </div>
                                        <div>
                                            <x-input-label value="Catatan" />
                                            <input type="text" name="notes" value="{{ $member->notes }}" class="mt-1 w-full rounded-md border-maroon-300 text-sm">
                                        </div>
                                        <div>
                                            <button type="submit" class="w-full rounded-md bg-maroon-800 px-4 py-2 text-sm font-medium text-white">Simpan</button>
                                        </div>
                                    </form>
                                    <p class="mt-2 text-xs text-maroon-700">Total gaji baru tidak boleh lebih kecil dari yang sudah dibayar (<x-money :amount="$member->paidAmount()" />).</p>
                                </td>
                            </tr>
                        @endcan

                        <tr x-show="panel === 'history'" x-cloak class="bg-maroon-50/50">
                            <td colspan="9" class="px-4 py-4">
                                @if ($member->transactions->isEmpty())
                                    <p class="text-sm text-maroon-700">Belum ada kasbon / pelunasan.</p>
                                @else
                                    <table class="min-w-full text-sm">
                                        <thead class="text-left text-xs font-semibold uppercase tracking-wide text-maroon-700">
                                        <tr>
                                            <th class="py-2 pr-4">Tanggal</th>
                                            <th class="py-2 pr-4">Jenis</th>
                                            <th class="py-2 pr-4 text-right">Nominal</th>
                                            <th class="py-2 pr-4">Catatan</th>
                                            <th class="py-2 pr-4">Dicatat</th>
                                            <th class="py-2 pr-4">Status</th>
                                            <th class="py-2">Action</th>
                                        </tr>
                                        </thead>
                                        <tbody class="divide-y divide-maroon-100">
                                        @foreach ($member->transactions as $transaction)
                                            <tr>
                                                <td class="py-2 pr-4 whitespace-nowrap">{{ date_id($transaction->transaction_date) }}</td>
                                                <td class="py-2 pr-4">{{ $transaction->type->label() }}</td>
                                                <td class="py-2 pr-4 text-right"><x-money :amount="$transaction->amount" /></td>
                                                <td class="py-2 pr-4">{{ $transaction->notes ?? '-' }}</td>
                                                <td class="py-2 pr-4">{{ $transaction->creator?->name ?? '-' }}</td>
                                                <td class="py-2 pr-4">
                                                    <x-status-badge :status="$transaction->status" />
                                                    @unless ($transaction->isPaid())
                                                        <div class="mt-1 text-xs text-maroon-700">{{ $transaction->cancel_reason }} — {{ $transaction->canceller?->name }}</div>
                                                    @endunless
                                                </td>
                                                <td class="py-2">
                                                    @can('cancel', $transaction)
                                                        <form method="POST" action="{{ route('man-power-transactions.cancel', $transaction) }}" class="flex gap-2"
                                                              onsubmit="return confirm('Batalkan transaksi ini? Nominal tidak lagi dihitung sebagai pengeluaran.');">
                                                            @csrf
                                                            <input type="text" name="cancel_reason" placeholder="Alasan batal" class="w-36 rounded-md border-maroon-300 text-xs" required minlength="3">
                                                            <button type="submit" class="text-xs font-medium text-maroon-800 hover:underline">Batalkan</button>
                                                        </form>
                                                    @else
                                                        -
                                                    @endcan
                                                </td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                @endif
                            </td>
                        </tr>
                        </tbody>
                    @endforeach
                    <tfoot class="bg-maroon-50 font-semibold">
                    <tr>
                        <td class="px-4 py-3" colspan="4">Total</td>
                        <td class="px-4 py-3 text-right"><x-money :amount="$totalSalary" /></td>
                        <td class="px-4 py-3 text-right"><x-money :amount="$totalKasbon" /></td>
                        <td class="px-4 py-3 text-right"><x-money :amount="$totalPelunasan" /></td>
                        <td class="px-4 py-3 text-right"><x-money :amount="$totalRemaining" /></td>
                        <td></td>
                    </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    @endif
</x-app-layout>
