{{-- File: resources/views/asset-management/master/suppliers/index.blade.php --}}
@extends('layouts.app')

@section('title')
Master Supplier
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <h4 class="py-3 mb-4"><span class="text-muted fw-light">Master Data Aset /</span> Supplier</h4>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Daftar Supplier</h5>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#supplierModal">
                <i class="ti ti-plus me-1"></i>Tambah Supplier
            </button>
        </div>
        <div class="card-datatable text-nowrap">
            <table id="suppliersTable" class="table table-hover">
                <thead class="border-top">
                    <tr>
                        <th>ID</th>
                        <th>Nama Supplier</th>
                        <th>Kontak Person</th>
                        <th>Telepon</th>
                        <th>Email</th>
                        <th>Alamat</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="supplierModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="supplierModalTitle">Tambah Supplier</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="supplierForm">
                @csrf
                <input type="hidden" name="_method" id="supplierMethod" value="POST">
                <input type="hidden" name="id" id="supplierId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Nama Supplier <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="contact_person" class="form-label">Kontak Person</label>
                        <input type="text" class="form-control" id="contact_person" name="contact_person">
                    </div>
                    <div class="mb-3">
                        <label for="phone" class="form-label">Telepon</label>
                        <input type="text" class="form-control" id="phone" name="phone">
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email">
                    </div>
                    <div class="mb-3">
                        <label for="address" class="form-label">Alamat</label>
                        <textarea class="form-control" id="address" name="address"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="notes" class="form-label">Catatan</label>
                        <textarea class="form-control" id="notes" name="notes"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button type="submit" class="btn btn-primary" id="submitSupplierBtn">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('after-scripts')
<script>
    $(document).ready(function() {
        var suppliersTable = $('#suppliersTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: '{{ route("asset-management.master.suppliers.get-data") }}',
            columns: [
                { data: 'id', name: 'id' },
                { data: 'name', name: 'name' },
                { data: 'contact_person', name: 'contact_person' },
                { data: 'phone', name: 'phone' },
                { data: 'email', name: 'email' },
                { data: 'address', name: 'address' },
                { data: 'actions', name: 'actions', orderable: false, searchable: false }
            ],
            dom: '<"row me-2"<"col-md-4"<"me-3"l>><"col-md-8 text-end d-flex align-items-center justify-content-end flex-md-row flex-column mb-3 mb-md-0"fB>><"table-responsive"t><"row mx-2"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
            language: {
                sLengthMenu: '_MENU_',
                search: '',
                searchPlaceholder: 'Cari Supplier...',
                info: 'Menampilkan _START_ sampai _END_ dari _TOTAL_ entri'
            }
        });

        // Add Supplier / Reset Form
        $('#supplierModal').on('show.bs.modal', function(event) {
            var button = $(event.relatedTarget);
            var modalTitle = $(this).find('#supplierModalTitle');
            var form = $(this).find('#supplierForm');
            form.trigger('reset');
            form.find('#supplierMethod').val('POST');
            form.find('#supplierId').val('');

            if (button.hasClass('edit-supplier')) {
                modalTitle.text('Edit Supplier');
                form.find('#supplierMethod').val('PUT');
                var supplierId = button.data('id');
                $.get('{{ url("asset-management/master/suppliers") }}/' + supplierId)
                    .done(function(data) {
                        form.find('#supplierId').val(data.id);
                        form.find('#name').val(data.name);
                        form.find('#contact_person').val(data.contact_person);
                        form.find('#phone').val(data.phone);
                        form.find('#email').val(data.email);
                        form.find('#address').val(data.address);
                        form.find('#notes').val(data.notes);
                    })
                    .fail(function(xhr) {
                        toastr.error('Gagal memuat data supplier.');
                    });
            } else {
                modalTitle.text('Tambah Supplier');
            }
        });

        // Submit Form
        $('#supplierForm').on('submit', function(e) {
            e.preventDefault();
            var form = $(this);
            var supplierId = form.find('#supplierId').val();
            var url = supplierId ? '{{ url("asset-management/master/suppliers") }}/' + supplierId : '{{ route("asset-management.master.suppliers.store") }}';
            var method = form.find('#supplierMethod').val();

            $.ajax({
                url: url,
                method: method,
                data: form.serialize(),
                success: function(response) {
                    toastr.success(response.message);
                    $('#supplierModal').modal('hide');
                    suppliersTable.ajax.reload();
                },
                error: function(xhr) {
                    var errors = xhr.responseJSON.errors;
                    if (errors) {
                        $.each(errors, function(key, value) {
                            toastr.error(value[0]);
                        });
                    } else {
                        toastr.error(xhr.responseJSON.message || 'Terjadi kesalahan.');
                    }
                }
            });
        });

        // Delete Supplier
        $(document).on('click', '.delete-supplier', function() {
            var supplierId = $(this).data('id');
            Swal.fire({
                title: 'Anda yakin?',
                text: "Data yang dihapus tidak dapat dikembalikan!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Ya, hapus!',
                cancelButtonText: 'Batal',
                customClass: {
                    confirmButton: 'btn btn-primary me-3',
                    cancelButton: 'btn btn-label-secondary'
                },
                buttonsStyling: false
            }).then(function(result) {
                if (result.value) {
                    $.ajax({
                        url: '{{ url("asset-management/master/suppliers") }}/' + supplierId,
                        method: 'DELETE',
                        data: { _token: '{{ csrf_token() }}' },
                        success: function(response) {
                            toastr.success(response.message);
                            suppliersTable.ajax.reload();
                        },
                        error: function(xhr) {
                            toastr.error(xhr.responseJSON.message || 'Gagal menghapus supplier.');
                        }
                    });
                }
            });
        });

        toastr.options = { closeButton: true, progressBar: true, positionClass: 'toast-top-right', timeOut: 5000 };
    });
</script>
@endpush