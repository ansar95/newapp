<x-app-layout>
    <x-slot name="header">{{ $billing->billing_number }}</x-slot>
    <x-slot name="subheader">{{ $project->code }} — Penagihan Client</x-slot>

    <x-breadcrumb :items="[
        ['label' => 'Projects', 'url' => route('projects.index')],
        ['label' => $project->code, 'url' => route('projects.show', $project)],
        ['label' => 'Penagihan', 'url' => route('projects.billing.index', $project)],
        ['label' => $billing->billing_number, 'url' => null],
    ]" />

    @php
        $received = $billing->receivedAmount();
        $outstanding = $billing->outstandingAmount();
        $canRecordPayment = auth()->user()->can('recordPayment', $billing);
    @endphp

    <div class="mb-6 rounded-xl border border-maroon-200 bg-white p-5 shadow-sm sm:p-6"
         x-data="{
             showCancel: {{ $errors->has('cancel_reason') && ! old('client_payment_id') ? 'true' : 'false' }},
             showSend: {{ $errors->hasAny(['invoice_number', 'invoice_date']) ? 'true' : 'false' }}
         }">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Penagihan Client</p>
                <div class="mt-1 flex flex-wrap items-center gap-3">
                    <h2 class="text-2xl font-semibold tracking-tight text-maroon-950">{{ $billing->billing_number }}</h2>
                    <x-status-badge :status="$billing->displayStatus()" />
                </div>
                <dl class="mt-3 grid gap-x-8 gap-y-2 text-sm sm:grid-cols-2">
                    <div><dt class="inline text-maroon-700">Termin:</dt> <dd class="inline font-medium">{{ $billing->term?->displayName() }}</dd></div>
                    <div><dt class="inline text-maroon-700">Client:</dt> <dd class="inline font-medium">{{ $project->client_name }}</dd></div>
                    <div><dt class="inline text-maroon-700">Tanggal Penagihan:</dt> <dd class="inline font-medium"><x-date-display :value="$billing->billing_date" /></dd></div>
                    <div><dt class="inline text-maroon-700">Jatuh Tempo:</dt> <dd class="inline font-medium">@if ($billing->due_date)<x-date-display :value="$billing->due_date" />@else - @endif</dd></div>
                    <div><dt class="inline text-maroon-700">No Invoice:</dt> <dd class="inline font-medium">{{ $billing->invoice_number ?? '-' }}</dd></div>
                    <div><dt class="inline text-maroon-700">Tanggal Invoice:</dt> <dd class="inline font-medium">@if ($billing->invoice_date)<x-date-display :value="$billing->invoice_date" />@else - @endif</dd></div>
                    <div>
                        <dt class="inline text-maroon-700">Lampiran Internal:</dt>
                        <dd class="inline font-medium">
                            @if ($billing->attachment)
                                <a href="{{ route('client-billings.attachment', $billing) }}" class="text-maroon-900 underline">Download</a>
                            @else
                                -
                            @endif
                        </dd>
                    </div>
                </dl>
                @if ($billing->description)
                    <p class="mt-3 text-sm text-maroon-800">{{ $billing->description }}</p>
                @endif
                <p class="mt-3 text-xs text-maroon-700">
                    Dibuat oleh {{ $billing->creator?->name ?? '-' }}
                    @if ($billing->submitted_at) · Submit {{ $billing->submitter?->name }} ({{ $billing->submitted_at->format('d/m/Y H:i') }}) @endif
                    @if ($billing->sent_at) · Dikirim {{ $billing->sender?->name }} ({{ $billing->sent_at->format('d/m/Y H:i') }}) @endif
                    @if ($billing->cancelled_at) · Dibatalkan {{ $billing->canceller?->name }} ({{ $billing->cancelled_at->format('d/m/Y H:i') }}) @endif
                </p>
            </div>

            <div class="w-full max-w-sm space-y-3">
                <div class="rounded-lg border border-maroon-200 bg-maroon-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-maroon-700">Nilai Penagihan</p>
                    <p class="mt-1 text-2xl font-bold text-maroon-950"><x-money :amount="$billing->billing_amount" /></p>
                    <p class="mt-1 text-xs text-maroon-800">
                        Received <x-money :amount="$received" class="font-semibold" />
                        · Outstanding <x-money :amount="$outstanding" class="font-semibold" />
                    </p>
                    @if ($term)
                        <p class="mt-2 border-t border-maroon-200 pt-2 text-xs text-maroon-800">
                            {{ $term->displayName() }}: <x-money :amount="$term->amount" />
                            · Sisa termin <x-money :amount="$summary->termRemaining($term)" class="font-semibold" />
                        </p>
                    @endif
                </div>
                <div class="flex flex-wrap gap-2">
                    @can('update', $billing)
                        <a href="{{ route('client-billings.edit', $billing) }}"
                           class="inline-flex items-center rounded-md border border-maroon-300 px-3 py-2 text-sm font-medium text-maroon-900 hover:bg-maroon-50">Edit</a>
                    @endcan
                    @can('submit', $billing)
                        <form method="POST" action="{{ route('client-billings.submit', $billing) }}"
                              onsubmit="return confirm('Submit penagihan ini? Setelah submit, penagihan tidak dapat diubah.');">
                            @csrf
                            <button type="submit" class="rounded-md bg-maroon-800 px-3 py-2 text-sm font-semibold text-white hover:bg-maroon-900">Submit</button>
                        </form>
                    @endcan
                    @can('send', $billing)
                        <button type="button" @click="showSend = !showSend"
                                class="rounded-md bg-maroon-800 px-3 py-2 text-sm font-semibold text-white hover:bg-maroon-900">Kirim ke Client</button>
                    @endcan
                    @can('returnToDraft', $billing)
                        <form method="POST" action="{{ route('client-billings.return-to-draft', $billing) }}"
                              onsubmit="return confirm('Kembalikan penagihan ke DRAFT untuk diperbaiki?');">
                            @csrf
                            <button type="submit" class="rounded-md border border-maroon-300 px-3 py-2 text-sm font-medium text-maroon-900 hover:bg-maroon-50">Kembalikan ke Draft</button>
                        </form>
                    @endcan
                    @can('cancel', $billing)
                        <button type="button" @click="showCancel = !showCancel"
                                class="rounded-md border border-maroon-300 px-3 py-2 text-sm font-medium text-maroon-900 hover:bg-maroon-50">Batalkan</button>
                    @endcan
                </div>
            </div>
        </div>

        @can('send', $billing)
            <form method="POST" action="{{ route('client-billings.send', $billing) }}" class="mt-4 rounded-lg border border-maroon-300 bg-maroon-50 p-4"
                  x-show="showSend" x-cloak
                  onsubmit="return confirm('Tandai penagihan sebagai SENT? Nilainya akan dihitung sebagai tagihan ke client.');">
                @csrf
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <x-input-label for="invoice_number" value="Nomor Invoice *" />
                        <x-text-input id="invoice_number" name="invoice_number" class="mt-1 block w-full text-sm" required maxlength="100"
                                      :value="old('invoice_number', $billing->invoice_number)" />
                        <x-input-error :messages="$errors->get('invoice_number')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="invoice_date" value="Tanggal Invoice *" />
                        <x-date-input id="invoice_date" name="invoice_date" class="mt-1 block w-full text-sm" required
                                      :value="old('invoice_date', now()->format('Y-m-d'))" />
                        <x-input-error :messages="$errors->get('invoice_date')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="send_due_date" value="Jatuh Tempo" />
                        <x-date-input id="send_due_date" name="due_date" class="mt-1 block w-full text-sm"
                                      :value="old('due_date', $billing->due_date?->format('Y-m-d'))" />
                        <x-input-error :messages="$errors->get('due_date')" class="mt-1" />
                    </div>
                </div>
                <div class="mt-3 flex justify-end">
                    <button type="submit" class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-semibold text-white hover:bg-maroon-900">Tandai SENT</button>
                </div>
            </form>
        @endcan

        @can('cancel', $billing)
            <form method="POST" action="{{ route('client-billings.cancel', $billing) }}" class="mt-4 rounded-lg border border-maroon-300 bg-maroon-50 p-4"
                  x-show="showCancel" x-cloak
                  onsubmit="return confirm('Batalkan penagihan ini? Penagihan CANCELLED tidak dihitung dan sisa termin akan kembali.');">
                @csrf
                <x-input-label for="cancel_reason" value="Alasan pembatalan *" />
                <textarea id="cancel_reason" name="cancel_reason" rows="2" required
                          class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600"
                          placeholder="Contoh: salah nilai, akan dibuat penagihan baru.">{{ old('client_payment_id') ? '' : old('cancel_reason') }}</textarea>
                @unless (old('client_payment_id'))
                    <x-input-error :messages="$errors->get('cancel_reason')" class="mt-1" />
                @endunless
                <div class="mt-3 flex justify-end">
                    <button type="submit" class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-semibold text-white hover:bg-maroon-900">Batalkan Penagihan</button>
                </div>
            </form>
        @endcan

        @if ($billing->cancel_reason)
            <div class="mt-4 rounded-lg border border-maroon-300 bg-white p-3 text-sm text-maroon-900">
                <span class="font-semibold">Alasan pembatalan:</span> {{ $billing->cancel_reason }}
            </div>
        @endif
    </div>

    @if ($documentMonitor)
        <div class="mb-6 overflow-hidden rounded-xl border border-maroon-200 bg-white shadow-sm">
            <div class="border-b border-maroon-100 px-4 py-3">
                <h3 class="text-base font-semibold text-maroon-950">Dokumen</h3>
                <p class="text-xs text-maroon-700">Berkas yang sudah terhubung ke penagihan ini. Unggahan baru disimpan di arsip dokumen project.</p>
            </div>
            @include('project-documents._table', ['documents' => $documentMonitor->documents, 'now' => $documentMonitor->now, 'showReference' => false])
        </div>
    @endif

    @if ($canViewPayments)
        <div class="overflow-hidden rounded-xl border border-maroon-200 bg-white shadow-sm"
             x-data="{ recording: {{ $errors->hasAny(['payment_date', 'amount', 'payment_number', 'bank_reference', 'proof_file']) ? 'true' : 'false' }} }">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-maroon-100 px-4 py-3">
                <div>
                    <h3 class="text-base font-semibold text-maroon-950">Pembayaran Client</h3>
                    <p class="text-xs text-maroon-700">Pembayaran hanya dapat dicatat untuk penagihan SENT dan tidak boleh melebihi outstanding.</p>
                </div>
                @if ($canRecordPayment)
                    <button type="button" @click="recording = !recording"
                            class="inline-flex items-center justify-center rounded-md bg-maroon-800 px-4 py-2 text-sm font-medium text-white hover:bg-maroon-900"
                            x-text="recording ? 'Tutup' : '+ Record Payment'">+ Record Payment</button>
                @endif
            </div>

            @if ($canRecordPayment)
                <form method="POST" action="{{ route('client-payments.store', $billing) }}" enctype="multipart/form-data"
                      class="border-b border-maroon-200 bg-maroon-50 p-4" x-show="recording" x-cloak
                      x-data="{ amount: @js((string) old('amount', '')), outstanding: {{ $outstanding }},
                                get exceeds() { return Math.round((parseFloat(this.amount) || 0) * 100) > Math.round(this.outstanding * 100); } }">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <x-input-label for="payment_date" value="Tanggal Pembayaran *" />
                            <x-date-input id="payment_date" name="payment_date" class="mt-1 block w-full text-sm" required
                                          :value="old('payment_date', now()->format('Y-m-d'))" />
                            <x-input-error :messages="$errors->get('payment_date')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="amount" value="Nilai Diterima (Rp) *" />
                            <input id="amount" type="number" name="amount" step="0.01" min="0.01" required x-model="amount"
                                   class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600">
                            <button type="button" class="mt-1 text-xs font-medium text-maroon-900 underline" @click="amount = String(outstanding)">Isi outstanding (<x-money :amount="$outstanding" />)</button>
                            <p class="mt-1 text-xs font-semibold text-maroon-900" x-show="exceeds" x-cloak role="alert">Nilai pembayaran melebihi outstanding penagihan.</p>
                            <x-input-error :messages="$errors->get('amount')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="payment_number" value="No Pembayaran" />
                            <x-text-input id="payment_number" name="payment_number" class="mt-1 block w-full text-sm" maxlength="50"
                                          :value="old('payment_number')" placeholder="Otomatis jika kosong" />
                            <x-input-error :messages="$errors->get('payment_number')" class="mt-1" />
                        </div>
                        <div>
                            <x-input-label for="bank_reference" value="Referensi Bank" />
                            <x-text-input id="bank_reference" name="bank_reference" class="mt-1 block w-full text-sm" maxlength="100"
                                          :value="old('bank_reference')" />
                            <x-input-error :messages="$errors->get('bank_reference')" class="mt-1" />
                        </div>
                        <div class="lg:col-span-2">
                            <x-input-label for="proof_file" value="Bukti Pembayaran (PDF/JPG/PNG, maks 5 MB)" />
                            <input id="proof_file" type="file" name="proof_file" accept=".pdf,.jpg,.jpeg,.png"
                                   class="mt-1 block w-full text-sm text-maroon-900 file:mr-3 file:rounded-md file:border-0 file:bg-maroon-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-maroon-900">
                            <x-input-error :messages="$errors->get('proof_file')" class="mt-1" />
                        </div>
                        <div class="lg:col-span-2">
                            <x-input-label for="payment_notes" value="Catatan" />
                            <x-text-input id="payment_notes" name="notes" class="mt-1 block w-full text-sm" :value="old('notes')" />
                        </div>
                    </div>
                    <div class="mt-4 flex justify-end">
                        <button type="submit" class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-semibold text-white hover:bg-maroon-900 disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="exceeds">Simpan Pembayaran</button>
                    </div>
                </form>
            @endif

            @if ($billing->payments->isEmpty())
                <p class="px-4 py-6 text-center text-sm text-maroon-700">Belum ada pembayaran untuk penagihan ini.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-maroon-200 text-sm">
                        <thead class="bg-maroon-50 text-left text-xs font-semibold uppercase tracking-wide text-maroon-700">
                        <tr>
                            <th class="px-4 py-3">Tanggal</th>
                            <th class="px-4 py-3">No Pembayaran</th>
                            <th class="px-4 py-3">Referensi Bank</th>
                            <th class="px-4 py-3 text-right">Nilai</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Action</th>
                        </tr>
                        </thead>
                        @foreach ($billing->payments as $payment)
                            <tbody class="divide-y divide-maroon-100 border-b border-maroon-100"
                                   x-data="{ cancelling: {{ (string) old('client_payment_id') === (string) $payment->id ? 'true' : 'false' }} }">
                            <tr class="{{ $payment->isReceived() ? 'hover:bg-maroon-50' : 'text-maroon-400' }}">
                                <td class="px-4 py-3 whitespace-nowrap"><x-date-display :value="$payment->payment_date" /></td>
                                <td class="px-4 py-3 font-medium text-maroon-950">
                                    {{ $payment->payment_number }}
                                    <span class="block text-xs font-normal text-maroon-700">
                                        Dicatat {{ $payment->creator?->name ?? '-' }}
                                        @if ($payment->cancelled_at) · Dibatalkan {{ $payment->canceller?->name }}: {{ $payment->cancel_reason }} @endif
                                    </span>
                                    @if ($payment->notes)
                                        <span class="block text-xs font-normal text-maroon-700">{{ $payment->notes }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ $payment->bank_reference ?? '-' }}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap"><x-money :amount="$payment->amount" /></td>
                                <td class="px-4 py-3"><x-status-badge :status="$payment->status" /></td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        @if ($payment->proof_file)
                                            <a href="{{ route('client-payments.proof', $payment) }}" class="text-xs font-medium text-maroon-900 underline">Bukti</a>
                                        @endif
                                        @can('cancel', $payment)
                                            <button type="button" @click="cancelling = !cancelling" class="text-xs font-medium text-maroon-900 underline">Batalkan</button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                            @can('cancel', $payment)
                                <tr x-show="cancelling" x-cloak>
                                    <td colspan="6" class="bg-maroon-50 p-4">
                                        <form method="POST" action="{{ route('client-payments.cancel', $payment) }}"
                                              onsubmit="return confirm('Batalkan pembayaran ini? Nilainya tidak lagi dihitung sebagai penerimaan.');">
                                            @csrf
                                            <input type="hidden" name="client_payment_id" value="{{ $payment->id }}">
                                            <x-input-label :for="'payment_cancel_reason_'.$payment->id" value="Alasan pembatalan *" />
                                            <textarea id="payment_cancel_reason_{{ $payment->id }}" name="cancel_reason" rows="2" required
                                                      class="mt-1 block w-full rounded-md border-maroon-300 text-sm shadow-sm focus:border-maroon-600 focus:ring-maroon-600">{{ (string) old('client_payment_id') === (string) $payment->id ? old('cancel_reason') : '' }}</textarea>
                                            @if ((string) old('client_payment_id') === (string) $payment->id)
                                                <x-input-error :messages="$errors->get('cancel_reason')" class="mt-1" />
                                            @endif
                                            <div class="mt-3 flex justify-end">
                                                <button type="submit" class="rounded-md bg-maroon-800 px-4 py-2 text-sm font-semibold text-white hover:bg-maroon-900">Batalkan Pembayaran</button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            @endcan
                            </tbody>
                        @endforeach
                    </table>
                </div>
            @endif
        </div>
    @endif
</x-app-layout>
