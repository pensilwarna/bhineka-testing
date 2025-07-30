{{-- File: resources/views/asset-management/settings/index.blade.php --}}
@extends('layouts.app')

@section('title')
Pengaturan Sistem Aset
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <h4 class="py-3 mb-4"><span class="text-muted fw-light">Asset Management /</span> Pengaturan Sistem</h4>

    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Konfigurasi Sistem</h5>
        </div>
        <div class="card-body">
            <form id="settingsForm">
                @csrf
                @foreach($settings as $category => $categorySettings)
                    <h6 class="border-bottom pb-2 mb-3 mt-4">{{ ucfirst(str_replace('_', ' ', $category)) }}</h6>
                    @foreach($categorySettings as $setting)
                        <div class="mb-3 row">
                            <label for="{{ $setting->key_name }}" class="col-sm-4 col-form-label">{{ $setting->description }}</label>
                            <div class="col-sm-8">
                                @if($setting->data_type === 'boolean')
                                    <div class="form-check form-switch mt-2">
                                        <input class="form-check-input" type="checkbox" id="{{ $setting->key_name }}" name="{{ $setting->key_name }}" value="1" {{ $setting->value === 'true' ? 'checked' : '' }}>
                                        <label class="form-check-label" for="{{ $setting->key_name }}">{{ $setting->value === 'true' ? 'Aktif' : 'Tidak Aktif' }}</label>
                                    </div>
                                @elseif($setting->data_type === 'decimal' || $setting->data_type === 'integer')
                                    <input type="number" step="{{ $setting->data_type === 'decimal' ? '0.01' : '1' }}" class="form-control" id="{{ $setting->key_name }}" name="{{ $setting->key_name }}" value="{{ $setting->value }}">
                                @else {{-- string or json --}}
                                    <input type="text" class="form-control" id="{{ $setting->key_name }}" name="{{ $setting->key_name }}" value="{{ $setting->value }}">
                                @endif
                                <small class="text-muted">{{ $setting->key_name }}</small>
                            </div>
                        </div>
                    @endforeach
                @endforeach
                <div class="mt-4 text-end">
                    <button type="submit" class="btn btn-primary" id="submitSettingsBtn">Simpan Pengaturan</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('after-scripts')
<script>
    $(document).ready(function() {
        $('#settingsForm').on('submit', function(e) {
            e.preventDefault();
            const form = $(this);
            const submitBtn = $('#submitSettingsBtn');
            submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Saving...');

            // Handle boolean checkboxes correctly
            const formData = new FormData(form[0]);
            form.find('input[type="checkbox"]').each(function() {
                if (!$(this).is(':checked')) {
                    // If checkbox is not checked, explicitly set its value to 'false'
                    // This is important because unchecked checkboxes are not sent in FormData by default
                    formData.set($(this).attr('name'), 'false');
                } else {
                    formData.set($(this).attr('name'), 'true'); // Ensure value is 'true'
                }
            });

            $.ajax({
                url: '{{ route("asset-management.settings.update") }}',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    toastr.success(response.message);
                    submitBtn.prop('disabled', false).html('Simpan Pengaturan');
                },
                error: function(xhr) {
                    var errors = xhr.responseJSON?.errors;
                    if (errors) {
                        $.each(errors, function(key, value) {
                            toastr.error(value[0]);
                        });
                    } else {
                        toastr.error(xhr.responseJSON?.message || 'Terjadi kesalahan saat menyimpan pengaturan.');
                    }
                    submitBtn.prop('disabled', false).html('Simpan Pengaturan');
                }
            });
        });

        toastr.options = { closeButton: true, progressBar: true, positionClass: 'toast-top-right', timeOut: 5000 };
    });
</script>
@endpush