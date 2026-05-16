@extends('layouts.app')

@section('title', 'Addresses — Settings')

@section('content')

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-0 fw-bold"><i class="bi bi-geo-alt me-2 text-primary"></i>Addresses</h4>
        <p class="text-muted small mb-0">Manage contact addresses used in your outreach.</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAddressModal">
        <i class="bi bi-plus-lg me-1"></i>Add Address
    </button>
</div>

<div class="card border-0 shadow-sm rounded-3">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:45px">#</th>
                    <th>Address</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Alt. Phone</th>
                    <th>Website</th>
                    <th style="width:110px">Status</th>
                    <th style="width:60px" class="text-center">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($addresses as $i => $addr)
                <tr>
                    <td class="text-muted small">{{ $i + 1 }}</td>
                    <td>{{ $addr->address }}</td>
                    <td class="small">
                        <a href="mailto:{{ $addr->email }}" class="text-decoration-none">{{ $addr->email }}</a>
                    </td>
                    <td class="small">{{ $addr->phone }}</td>
                    <td class="small text-muted">{{ $addr->alternate_phone ?: '—' }}</td>
                    <td class="small">
                        @if($addr->website)
                            <a href="{{ $addr->website }}" target="_blank" class="text-decoration-none text-truncate d-inline-block" style="max-width:140px">
                                <i class="bi bi-box-arrow-up-right me-1"></i>{{ $addr->website }}
                            </a>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>
                        @if($addr->status === 'active')
                            <span class="badge rounded-pill bg-success">Active</span>
                        @else
                            <span class="badge rounded-pill bg-secondary">Inactive</span>
                        @endif
                    </td>
                    <td class="text-center">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-light border rounded-circle px-2"
                                    data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                <li>
                                    <button class="dropdown-item btn-edit-address"
                                            data-id="{{ $addr->id }}"
                                            data-address="{{ $addr->address }}"
                                            data-email="{{ $addr->email }}"
                                            data-phone="{{ $addr->phone }}"
                                            data-alternate_phone="{{ $addr->alternate_phone }}"
                                            data-website="{{ $addr->website }}"
                                            data-status="{{ $addr->status }}">
                                        <i class="bi bi-pencil me-2 text-warning"></i>Edit
                                    </button>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="POST" action="{{ route('addresses.destroy', $addr->id) }}"
                                          onsubmit="return confirm('Delete this address?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="dropdown-item text-danger">
                                            <i class="bi bi-trash me-2"></i>Delete
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="text-center text-muted py-5">
                        <i class="bi bi-geo-alt display-6 d-block mb-2 opacity-25"></i>
                        No addresses yet. Add one to get started.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ===================== ADD MODAL ===================== --}}
<div class="modal fade" id="addAddressModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="{{ route('addresses.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2 text-primary"></i>Add Address</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Address <span class="text-danger">*</span></label>
                            <textarea name="address" class="form-control @error('address') is-invalid @enderror"
                                      rows="2" placeholder="e.g. 123 Main St, City, Country" required>{{ old('address') }}</textarea>
                            @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                                   placeholder="contact@example.com" value="{{ old('email') }}" required>
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="status" class="form-select">
                                <option value="active" {{ old('status') === 'inactive' ? '' : 'selected' }}>Active</option>
                                <option value="inactive" {{ old('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Phone <span class="text-danger">*</span></label>
                            <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror"
                                   placeholder="+1 234 567 8900" value="{{ old('phone') }}" required>
                            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Alternate Phone</label>
                            <input type="text" name="alternate_phone" class="form-control"
                                   placeholder="+1 234 567 8901" value="{{ old('alternate_phone') }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Website</label>
                            <input type="url" name="website" class="form-control @error('website') is-invalid @enderror"
                                   placeholder="https://example.com" value="{{ old('website') }}">
                            @error('website')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Add</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- ===================== EDIT MODAL ===================== --}}
<div class="modal fade" id="editAddressModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <form method="POST" id="editAddressForm">
                @csrf @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2 text-warning"></i>Edit Address</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Address <span class="text-danger">*</span></label>
                            <textarea name="address" id="edit_address" class="form-control" rows="2" required></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" id="edit_addr_email" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="status" id="edit_addr_status" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Phone <span class="text-danger">*</span></label>
                            <input type="text" name="phone" id="edit_phone" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Alternate Phone</label>
                            <input type="text" name="alternate_phone" id="edit_alternate_phone" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Website</label>
                            <input type="url" name="website" id="edit_website_addr" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning"><i class="bi bi-save me-1"></i>Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    document.querySelectorAll('.btn-edit-address').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const d = this.dataset;
            document.getElementById('edit_address').value          = d.address         || '';
            document.getElementById('edit_addr_email').value       = d.email           || '';
            document.getElementById('edit_phone').value            = d.phone           || '';
            document.getElementById('edit_alternate_phone').value  = d.alternate_phone || '';
            document.getElementById('edit_website_addr').value     = d.website         || '';
            document.getElementById('edit_addr_status').value      = d.status          || 'active';
            document.getElementById('editAddressForm').action      = '/settings/addresses/' + d.id;
            new bootstrap.Modal(document.getElementById('editAddressModal')).show();
        });
    });

    @if($errors->any())
        new bootstrap.Modal(document.getElementById('addAddressModal')).show();
    @endif
</script>
@endpush
