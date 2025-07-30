{{-- File: resources/views/asset-management/master/warehouses/index.blade.php --}}
@extends('layouts.app')

@section('title')
Master Gudang
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <h4 class="py-3 mb-4"><span class="text-muted fw-light">Master Data Aset /</span> Gudang</h4>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Daftar Gudang</h5>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#warehouseModal">
                <i class="ti ti-plus me-1"></i>Tambah Gudang
            </button>
        </div>
        <div class="card-datatable text-nowrap">
            <table id="warehousesTable" class="table table-hover">
                <thead class="border-top">
                    <tr>
                        <th>ID</th>
                        <th>Nama Gudang</th>
                        <th>Lokasi</th>
                        <th>Kantor Terkait</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="warehouseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="warehouseModalTitle">Tambah Gudang</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="warehouseForm">
                @csrf
                <input type="hidden" name="_method" id="warehouseMethod" value="POST">
                <input type="hidden" name="id" id="warehouseId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Nama Gudang <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="location" class="form-label">Lokasi</label>
                        <textarea class="form-control" id="location" name="location"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="office_id" class="form-label">Kantor Terkait (Opsional)</label>
                        <select class="form-select" id="office_id" name="office_id">
                            <option value="">Tidak Terkait Kantor</option>
                            @foreach($offices as $office)
                            <option value="{{ $office->id }}">{{ $office->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button type="submit" class="btn btn-primary" id="submitWarehouseBtn">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('after-scripts')
<script>
    $(document).ready(function() {
        var warehousesTable = $('#warehousesTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: '{{ route("asset-management.master.warehouses.get-data") }}',
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
                { data: 'location', name: 'location' },
                { data: 'office_name', name: 'office.name', defaultContent: '-' },
                { data: 'actions', name: 'actions', orderable: false, searchable: false }
            ],
            dom: '<"row me-2"<"col-md-4"<"me-3"l>><"col-md-8 text-end d-flex align-items-center justify-content-end flex-md-row flex-column mb-3 mb-md-0"fB>><"table-responsive"t><"row mx-2"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
            lengthMenu: [7, 10, 25, 50, 70, 100],
            displayLength: 10,
            language: {
                sLengthMenu: '_MENU_',
                search: '',
                searchPlaceholder: 'Search Warehouse...',
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

        // Add Warehouse / Reset Form
        $('#warehouseModal').on('show.bs.modal', function(event) {
            var button = $(event.relatedTarget);
            var modalTitle = $(this).find('#warehouseModalTitle');
            var form = $(this).find('#warehouseForm');
            form.trigger('reset');
            form.find('#warehouseMethod').val('POST');
            form.find('#warehouseId').val('');
            form.find('#office_id').val(''); // Clear office selection

            if (button.hasClass('edit-warehouse')) {
                modalTitle.text('Edit Gudang');
                form.find('#warehouseMethod').val('PUT');
                var warehouseId = button.data('id');
                $.get('{{ url("asset-management/master/warehouses") }}/' + warehouseId)
                    .done(function(data) {
                        form.find('#warehouseId').val(data.id);
                        form.find('#name').val(data.name);
                        form.find('#location').val(data.location);
                        form.find('#office_id').val(data.office_id);
                    })
                    .fail(function(xhr) {
                        toastr.error('Gagal memuat data gudang.');
                    });
            } else {
                modalTitle.text('Tambah Gudang');
            }
        });

        // Submit Form
        $('#warehouseForm').on('submit', function(e) {
            e.preventDefault();
            var form = $(this);
            var warehouseId = form.find('#warehouseId').val();
            var url = warehouseId ? '{{ url("asset-management/master/warehouses") }}/' + warehouseId : '{{ route("asset-management.master.warehouses.store") }}';
            var method = form.find('#warehouseMethod').val();

            $.ajax({
                url: url,
                method: method,
                data: form.serialize(),
                success: function(response) {
                    toastr.success(response.message);
                    $('#warehouseModal').modal('hide');
                    warehousesTable.ajax.reload();
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

        // Delete Warehouse
        $(document).on('click', '.delete-warehouse', function() {
            var warehouseId = $(this).data('id');
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
                        url: '{{ url("asset-management/master/warehouses") }}/' + warehouseId,
                        method: 'DELETE',
                        data: { _token: '{{ csrf_token() }}' },
                        success: function(response) {
                            toastr.success(response.message);
                            warehousesTable.ajax.reload();
                        },
                        error: function(xhr) {
                            toastr.error(xhr.responseJSON.message || 'Gagal menghapus gudang.');
                        }
                    });
                }
            });
        });

        toastr.options = { closeButton: true, progressBar: true, positionClass: 'toast-top-right', timeOut: 5000 };
    });
</script>
@endpush