{{--
    Bulk send buttons for a list screen (GMB Leads, Deals, Leads). The row
    checkboxes (.wa-row-check) are in the list's table partial; selection,
    the modal and sending live in whatsapp/_compose.blade.php.

    @param string   $bulkUrl     the list's bulk endpoint (gmb-leads.whatsapp / cash-leads.bulk-send / leads.bulk-email)
    @param string   $source      gmb | deal | lead - the record type, used to load the right account's WhatsApp templates
    @param string   $label       plural noun for the modal's summary ("GMB leads", "deals", "leads")
    @param string[] $channels    what the list's rows can be sent: whatsapp, email (default: whatsapp)
    @param string   $totalFrom   element carrying data-total, the count of rows matching the filters
                                 (default: the ajax-filters.js list, [data-ajax-root] .ajax-content)
    @param string   $rowsRegion  element whose content is swapped when the list reloads
                                 (default: [data-ajax-root] .ajax-region)
    @param bool     $selectedOnly  only the "selected" button - no "send to every match" (Leads:
                                   email goes to the ticked leads and nobody else)
--}}
@php
    // WhatsApp needs its own module; email comes with the list.
    $bulkChannels = array_values(array_filter(
        $channels ?? ['whatsapp'],
        fn (string $channel) => $channel !== 'whatsapp' || auth()->user()?->can('module', 'whatsapp'),
    ));
    $bulkEmailOn  = in_array('email', $bulkChannels, true);
    $bulkMailOnly = $bulkChannels === ['email'];
@endphp
@if($bulkChannels)
<span class="d-inline-flex flex-wrap align-items-center gap-1" data-wa-bulk
      data-url="{{ $bulkUrl }}" data-source="{{ $source }}" data-label="{{ $label }}"
      data-channels="{{ implode(',', $bulkChannels) }}"
      @isset($totalFrom) data-total-from="{{ $totalFrom }}" @endisset
      @isset($rowsRegion) data-rows-region="{{ $rowsRegion }}" @endisset>
    @if(! empty($selectedOnly))
        {{-- Shown once rows are ticked (whatsapp/_compose updateSelection) --}}
        <button type="button" class="btn btn-primary btn-sm m-1 btn-wa-bulk d-none" data-mode="selected">
            <i class="bi {{ $bulkMailOnly ? 'bi-envelope' : 'bi-send' }} me-1"></i>{{ $bulkMailOnly ? 'Send Bulk Email' : 'Bulk Send' }}
            (<span data-wa-selected-count>0</span>)
        </button>
    @elseif($bulkMailOnly)
        <button type="button" class="btn btn-primary btn-sm m-1 btn-wa-bulk d-none" data-mode="selected">
            <i class="bi bi-envelope me-1"></i>Email <span data-wa-selected-count>0</span> selected
        </button>
        <button type="button" class="btn btn-primary light btn-sm m-1 btn-wa-bulk" data-mode="all"
                title="Send an email to every {{ \Illuminate\Support\Str::singular($label) }} matching the current filters">
            <i class="bi bi-envelope me-1"></i>Bulk Email
        </button>
    @elseif($bulkEmailOn)
        <button type="button" class="btn btn-primary btn-sm m-1 btn-wa-bulk d-none" data-mode="selected">
            <i class="bi bi-send me-1"></i>Send to <span data-wa-selected-count>0</span> selected
        </button>
        <button type="button" class="btn btn-primary light btn-sm m-1 btn-wa-bulk" data-mode="all"
                title="Send WhatsApp and/or email to every contact matching the current filters">
            <i class="bi bi-send me-1"></i>Bulk Send
        </button>
    @else
        <button type="button" class="btn btn-success btn-sm m-1 btn-wa-bulk d-none" data-mode="selected">
            <i class="bi bi-whatsapp me-1"></i>WhatsApp <span data-wa-selected-count>0</span> selected
        </button>
        <button type="button" class="btn btn-success light btn-sm m-1 btn-wa-bulk" data-mode="all"
                title="Send an approved template to every contact matching the current filters">
            <i class="bi bi-whatsapp me-1"></i>Bulk WhatsApp
        </button>
    @endif
</span>
@endif
