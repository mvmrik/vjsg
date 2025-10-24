@extends('layouts.app')

@section('content')
  <div class="container py-6">
    <h1>Release {{ $version }}</h1>
    <p><small>Published: {{ $date }}</small></p>

    <div class="prose max-w-none">
      {!! $html !!}
    </div>

    <div class="mt-4">
      <a href="{{ route('releases.index') }}">&larr; Back to releases</a>
    </div>
  </div>
@endsection
