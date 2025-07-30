{{-- File: resources/views/asset-management/master/offices/index.blade.php --}}
@extends('layouts.app')

@section('title')
Master Kantor
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <h4 class="py-3 mb-4"><span class="text-muted fw-light">Master Data /</span> Kantor</h4>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Daftar Kantor</h5>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#officeModal">
                <i class="ti ti-plus me-1"></i>Tambah Kantor
            </button>
        </div>
        <div class="card-datatable text-nowrap">
            <table id="officesTable" class="table table-hover">
                <thead class="border-top">
                    <tr>
                        <th>ID</th>
                        <th>Nama Kantor</th>
                        <th>Alamat</th>
                        <th>Telepon</th>
                        <th>Email</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="officeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="officeModalTitle">Tambah Kantor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="officeForm">
                @csrf
                <input type="hidden" name="_method" id="officeMethod" value="POST">
                <input type="hidden" name="id" id="officeId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Nama Kantor <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="address" class="form-label">Alamat</label>
                        <textarea class="form-control" id="address" name="address"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="phone" class="form-label">Telepon</label>
                        <input type="text" class="form-control" id="phone" name="phone">
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button type="submit" class="btn btn-primary" id="submitOfficeBtn">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('after-scripts')
<script>
    $(document).ready(function() {
        var officesTable = $('#officesTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: '{{ route("asset-management.master.offices.get-data") }}',
            columns: [
                {
                    data: null,
                    name: 'no', 
                    orderable: false, 
                    searchable: false,
                    render: function (data, type, row, meta) {
                        return meta.row + meta.settings._iDisplayStart + 1;
                    }
                },
                { data: 'name', name: 'name' },
                { data: 'address', name: 'address' },
                { data: 'phone', name: 'phone' },
                { data: 'email', name: 'email' },
                { data: 'actions', name: 'actions', orderable: false, searchable: false }
            ],
            dom: '<"row me-2"<"col-md-4"<"me-3"l>><"col-md-8 text-end d-flex align-items-center justify-content-end flex-md-row flex-column mb-3 mb-md-0"fB>><"table-responsive"t><"row mx-2"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
            lengthMenu: [7, 10, 25, 50, 70, 100],
            displayLength: 10,
            language: {
                sLengthMenu: '_MENU_',
                search: '',
                searchPlaceholder: 'Search Office...',
                info: 'Displaying _START_ to _END_ of _TOTAL_ entries'
            },
            buttons: [
                {
                    extend: 'collection',
                    className: 'btn btn-label-secondary btn-sm dropdown-toggle waves-effect waves-light mx-2',
                    text: '<i class="ti ti-download me-1 ti-xs"></i>Export',
                    buttons: [
                        { extend: 'print', text: '<i class="ti ti-printer me-2"></i>Print', className: 'dropdown-item', exportOptions: { columns: [0, 1, 2, 3, 4, 5] } },
                        { extend: 'csv', text: '<i class="ti ti-file me-2"></i>Csv', className: 'dropdown-item', exportOptions: { columns: [0, 1, 2, 3, 4, 5] } },
                        { extend: 'excel', text: '<i class="ti ti-file-export me-2"></i>Excel', className: 'dropdown-item', exportOptions: { columns: [0, 1, 2, 3, 4, 5] } },
                        { extend: 'pdf', text: '<i class="ti ti-file-text me-2"></i>Pdf', className: 'dropdown-item', exportOptions: { columns: [0, 1, 2, 3, 4, 5] } },
                        { extend: 'copy', text: '<i class="ti ti-copy me-2"></i>Copy', className: 'dropdown-item', exportOptions: { columns: [0, 1, 2, 3, 4, 5] } }
                    ]
                },
            ],
        });

        // Add Office / Reset Form
        $('#officeModal').on('show.bs.modal', function(event) {
            var button = $(event.relatedTarget);
            var modalTitle = $(this).find('#officeModalTitle');
            var form = $(this).find('#officeForm');
            form.trigger('reset');
            form.find('#officeMethod').val('POST');
            form.find('#officeId').val('');
            form.find('input[name="name"]').prop('readonly', false); // Ensure name is editable for new

            if (button.hasClass('edit-office')) {
                modalTitle.text('Edit Kantor');
                form.find('#officeMethod').val('PUT');
                var officeId = button.data('id');
                $.get('{{ url("asset-management/master/offices") }}/' + officeId)
                    .done(function(data) {
                        form.find('#officeId').val(data.id);
                        form.find('#name').val(data.name);
                        form.find('#address').val(data.address);
                        form.find('#phone').val(data.phone);
                        form.find('#email').val(data.email);
                    })
                    .fail(function(xhr) {
                        toastr.error('Gagal memuat data kantor.');
                    });
            } else {
                modalTitle.text('Tambah Kantor');
            }
        });

        // Submit Form
        $('#officeForm').on('submit', function(e) {
            e.preventDefault();
            var form = $(this);
            var url = form.find('#officeId').val() ? '{{ url("asset-management/master/offices") }}/' + form.find('#officeId').val() : '{{ route("asset-management.master.offices.store") }}';
            var method = form.find('#officeMethod').val();

            $.ajax({
                url: url,
                method: method,
                data: form.serialize(),
                success: function(response) {
                    toastr.success(response.message);
                    $('#officeModal').modal('hide');
                    officesTable.ajax.reload();
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

        // Delete Office
        $(document).on('click', '.delete-office', function() {
            var officeId = $(this).data('id');
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
                        url: '{{ url("asset-management/master/offices") }}/' + officeId,
                        method: 'DELETE',
                        data: { _token: '{{ csrf_token() }}' },
                        success: function(response) {
                            toastr.success(response.message);
                            officesTable.ajax.reload();
                        },
                        error: function(xhr) {
                            toastr.error(xhr.responseJSON.message || 'Gagal menghapus kantor.');
                        }
                    });
                }
            });
        });

        toastr.options = { closeButton: true, progressBar: true, positionClass: 'toast-top-right', timeOut: 5000 };
    });
</script>
@endpush