@extends('front.layouts.frontend', ['seo' => ['title' => 'My Card']])

@section('content')
<div class="container py-5" style="min-height: 60vh; display: flex; flex-direction: column; justify-content: center; align-items: center;">
    <div class="card shadow-sm p-4 text-center" style="max-width: 400px; width: 100%;">
        <div class="mb-4">
            <h2 class="h4 mb-1">Welcome, {{ auth()->user()->name ?? 'User' }}!</h2>
            <p class="text-muted small">{{ auth()->user()->email }}</p>
        </div>

        <div class="mb-4">
            <p>This is your personal card page.</p>
        </div>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-dark w-100">
                Log Out
            </button>
        </form>
    </div>
</div>
@endsection
