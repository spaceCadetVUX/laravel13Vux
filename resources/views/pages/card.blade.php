@extends('layouts.frontend')

@section('content')
<div class="container py-5" style="min-height: 60vh; display: flex; align-items: center; justify-content: center;">
    <div class="card shadow-sm p-4 text-center" style="max-width: 400px; width: 100%;">
        <h2>Welcome, {{ auth()->user()->name ?? 'User' }}!</h2>
        <p>{{ auth()->user()->email }}</p>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-dark w-100">Log Out</button>
        </form>
    </div>
</div>
@endsection
