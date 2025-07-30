{{-- File: resources/views/asset-management/master/asset-categories/index.blade.php --}}
@extends('layouts.app')

@section('title')
Master Kategori Aset
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <h4 class="py-3 mb-4"><span class="text-muted fw-light">Master Data Aset /</span> Kategori Aset</h4>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Daftar Kategori Aset</h5>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#assetCategoryModal">
                <i class="ti ti-plus me-1"></i>Tambah Kategori
            </button>
        </div>
        <div class="card-datatable text-nowrap">
            <table id="assetCategoriesTable" class="table table-hover">
                <thead class="border-top">
                    <tr>
                        <th>ID</th>
                        <th>Nama Kategori</th>
                        <th>Satuan Unit</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="assetCategoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="assetCategoryModalTitle">Tambah Kategori Aset</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="assetCategoryForm">
                @csrf
                <input type="hidden" name="_method" id="assetCategoryMethod" value="POST">
                <input type="hidden" name="id" id="assetCategoryId">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Nama Kategori <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="unit" class="form-label">Satuan Unit (e.g., meter, unit, pcs) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="unit" name="unit" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Tutup</button>
                    <button type="submit" class="btn btn-primary" id="submitAssetCategoryBtn">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('after-scripts')
<script>
    $(document).ready(function() {
        var assetCategoriesTable = $('#assetCategoriesTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: '{{ route("asset-management.master.categories.get-data") }}',
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
                { data: 'unit', name: 'unit' },
                { data: 'actions', name: 'actions', orderable: false, searchable: false }
            ],
            dom: '<"row me-2"<"col-md-4"<"me-3"l>><"col-md-8 text-end d-flex align-items-center justify-content-end flex-md-row flex-column mb-3 mb-md-0"fB>><"table-responsive"t><"row mx-2"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
            lengthMenu: [7, 10, 25, 50, 70, 100],
            displayLength: 10,
            language: {
                sLengthMenu: '_MENU_',
                search: '',
                searchPlaceholder: 'Search Aset Category...',
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

        // Add Category / Reset Form
        $('#assetCategoryModal').on('show.bs.modal', function(event) {
            var button = $(event.relatedTarget);
            var modalTitle = $(this).find('#assetCategoryModalTitle');
            var form = $(this).find('#assetCategoryForm');
            form.trigger('reset');
            form.find('#assetCategoryMethod').val('POST');
            form.find('#assetCategoryId').val('');

            if (button.hasClass('edit-asset-category')) {
                modalTitle.text('Edit Kategori Aset');
                form.find('#assetCategoryMethod').val('PUT');
                var categoryId = button.data('id');
                $.get('{{ url("asset-management/master/asset-categories") }}/' + categoryId)
                    .done(function(data) {
                        form.find('#assetCategoryId').val(data.id);
                        form.find('#name').val(data.name);
                        form.find('#unit').val(data.unit);
                    })
                    .fail(function(xhr) {
                        toastr.error('Gagal memuat data kategori aset.');
                    });
            } else {
                modalTitle.text('Tambah Kategori Aset');
            }
        });

        // Submit Form
        $('#assetCategoryForm').on('submit', function(e) {
            e.preventDefault();
            var form = $(this);
            var categoryId = form.find('#assetCategoryId').val();
            var url = categoryId ? '{{ url("asset-management/master/asset-categories") }}/' + categoryId : '{{ route("asset-management.master.categories.store") }}';
            var method = form.find('#assetCategoryMethod').val();

            $.ajax({
                url: url,
                method: method,
                data: form.serialize(),
                success: function(response) {
                    toastr.success(response.message);
                    $('#assetCategoryModal').modal('hide');
                    assetCategoriesTable.ajax.reload();
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

        // Delete Category
        $(document).on('click', '.delete-asset-category', function() {
            var categoryId = $(this).data('id');
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
                        url: '{{ url("asset-management/master/asset-categories") }}/' + categoryId,
                        method: 'DELETE',
                        data: { _token: '{{ csrf_token() }}' },
                        success: function(response) {
                            toastr.success(response.message);
                            assetCategoriesTable.ajax.reload();
                        },
                        error: function(xhr) {
                            toastr.error(xhr.responseJSON.message || 'Gagal menghapus kategori aset.');
                        }
                    });
                }
            });
        });

        toastr.options = { closeButton: true, progressBar: true, positionClass: 'toast-top-right', timeOut: 5000 };
    });
</script>
@endpush