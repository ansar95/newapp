@props(['status'])

@php
    $value = $status instanceof \BackedEnum ? $status->value : (string) $status;
    $classes = match ($value) {
        'ACTIVE', 'APPROVED', 'PAID', 'COMPLETED', 'RESOLVED', 'IN_USE', 'AVAILABLE', 'PURCHASED', 'GOOD', 'CONFIRMED', 'RECEIVED' => 'bg-maroon-800 text-white ring-maroon-900',
        'WAITING_APPROVAL', 'PLANNED', 'OPEN', 'PENDING', 'NOT_STARTED', 'EXISTING', 'FAIR', 'READY_TO_PAY', 'IN_PROGRESS', 'PURCHASE', 'REQUESTED', 'POOR', 'SUBMITTED',
        'READY', 'PARTIALLY_BILLED', 'FULLY_BILLED', 'SENT', 'PARTIALLY_PAID', 'UNDER_REVIEW', 'RESUBMITTED' => 'bg-maroon-100 text-maroon-900 ring-maroon-300',
        'DRAFT', 'INACTIVE', 'RETURNED', 'RELEASED', 'CANCELLED', 'VOID', 'DAMAGED', 'REVISION_REQUIRED', 'NOT_READY' => 'bg-white text-maroon-800 ring-maroon-300',
        default => 'bg-maroon-50 text-maroon-900 ring-maroon-200',
    };
    $label = ($status instanceof \BackedEnum && method_exists($status, 'label'))
        ? $status->label()
        : str_replace('_', ' ', $value);
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-md px-2.5 py-1 text-xs font-bold ring-1 ring-inset {$classes}"]) }}
      title="{{ $label }}">
    {{ $label }}
</span>
