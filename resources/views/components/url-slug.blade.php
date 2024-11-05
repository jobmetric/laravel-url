<!--begin::Slug-->
<div class="card card-flush py-4">
    <div class="card-header">
        <div class="card-title">
            <span class="fs-5 fw-bold">{{ trans('url::base.components.url_slug.title') }}</span>
        </div>
    </div>
    <div class="card-body pt-0">
        <input type="text" name="slug" class="form-control" placeholder="{{ trans('url::base.components.url_slug.placeholder') }}" value="{{ $value }}">
        @error('slug')
            <div class="form-errors text-danger fs-7 mt-2">{{ $message }}</div>
        @enderror
        <div class="mt-5 text-gray-600 fs-7">{{ trans('url::base.components.url_slug.description') }}</div>
    </div>
</div>
<!--end::Slug-->
