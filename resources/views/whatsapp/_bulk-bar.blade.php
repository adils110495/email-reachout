{{--
    Bulk WhatsApp buttons for a list screen (GMB Leads, Deals). The row
    checkboxes (.wa-row-check) are in the list's table partial; selection,
    the modal and sending live in whatsapp/_compose.blade.php.

    @param string $bulkUrl  the list's bulk endpoint (gmb-leads.whatsapp / cash-leads.whatsapp)
    @param string $source   gmb | deal - the record type, used to load the right account's templates
    @param string $label    plural noun for the modal's summary ("GMB leads", "deals")
--}}
@can('module', 'whatsapp')
<span class="d-inline-flex flex-wrap align-items-center gap-1" data-wa-bulk
      data-url="{{ $bulkUrl }}" data-source="{{ $source }}" data-label="{{ $label }}">
    <button type="button" class="btn btn-success btn-sm m-1 btn-wa-bulk d-none" data-mode="selected">
        <i class="bi bi-whatsapp me-1"></i>WhatsApp <span data-wa-selected-count>0</span> selected
    </button>
    <button type="button" class="btn btn-success light btn-sm m-1 btn-wa-bulk" data-mode="all"
            title="Send an approved template to every contact matching the current filters">
        <i class="bi bi-whatsapp me-1"></i>Bulk WhatsApp
    </button>
</span>
@endcan
