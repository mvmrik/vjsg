@extends('layouts.app')

@section('content')
  <div class="container py-6">
    <h1>Releases</h1>

    <table class="table table-striped">
      <thead>
        <tr>
          <th>Version</th>
          <th>Date</th>
          <th>Description</th>
        </tr>
      </thead>
      <tbody>
        @foreach($releases as $release)
          <tr>
            <td>
              <a href="{{ route('releases.show', ['version' => $release['version']]) }}">{{ $release['version'] }}</a>
            </td>
            <td>{{ $release['date'] }}</td>
            <td>{!! $release['excerpt'] !!}</td>
          </tr>
        @endforeach
      </tbody>
    </table>

    <div class="mt-4">
      {{ $releases->links() }}
    </div>
  </div>
@endsection
