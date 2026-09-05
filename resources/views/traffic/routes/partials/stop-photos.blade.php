@php
  $photoCount = $stop->photos->count();
@endphp
<form method="POST"
      action="{{ route('traffic.routes.stops.photos.store', ['route' => $route, 'stop' => $stop]) }}"
      enctype="multipart/form-data"
      class="d-none"
      data-stop-photos-form
      data-stop-id="{{ $stop->id }}">
  @csrf
  <input type="file"
         name="photos[]"
         accept="image/*"
         capture="environment"
         multiple
         data-stop-photo-input>
</form>
@if($photoCount)
  <div class="mt-3 d-flex flex-wrap align-items-center gap-2" data-stop-photos-list>
    <span class="small text-muted w-100">Fotos registradas:</span>
    <div class="d-flex flex-wrap gap-2">
      @foreach($stop->photos as $photo)
        <div class="stop-photo-thumb position-relative">
          <a class="d-block border rounded overflow-hidden" style="width: 80px; height: 80px;" title="{{ optional($photo->created_at)->format('d/m/Y H:i') }}{{ $photo->user ? ' · ' . $photo->user->name : '' }}" href="{{ storage_image_url($photo->path) }}" target="_blank" rel="noreferrer">
            <img src="{{ storage_image_url($photo->path) }}"
                 alt="Foto de entrega"
                 loading="lazy"
                 class="w-100 h-100"
                 style="object-fit: cover;">
          </a>
          <form method="POST"
                action="{{ route('traffic.routes.stops.photos.destroy', ['route' => $route, 'stop' => $stop, 'photo' => $photo]) }}"
                class="stop-photo-delete-form"
                data-stop-photo-delete>
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-sm btn-outline-danger stop-photo-delete-btn" title="Eliminar foto">
              <i class="fa-solid fa-trash-can"></i>
            </button>
          </form>
        </div>
      @endforeach
    </div>
  </div>
@endif
