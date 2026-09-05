@extends('layouts.app')

@section('title', 'Términos y condiciones')

@section('content')
  <div class="row">
    <div class="col-lg-10 offset-lg-1">
      <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
          <h1 class="h5 mb-0">Términos y condiciones</h1>
        </div>
        <div class="card-body">
          {!! nl2br(e($term->content)) !!}
        </div>
      </div>
    </div>
  </div>
@endsection
