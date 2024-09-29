@extends('layouts.app')

@section('title', 'Register')

@section('content')
<canvas id="backgroundCanvas" style="position:absolute; top:0; left:0; z-index:-1;"></canvas>
<div class="container d-flex justify-content-center align-items-center" style="min-height: 100vh;">
    <div class="card bg-dark text-light p-4" style="width: 100%; max-width: 400px;">
        <h3 class="text-center mb-4">Register</h3>

        <form method="POST" action="{{ route('register') }}">
            @csrf

            <!-- Name Field -->
            <div class="form-group mb-3">
                <label for="name" class="form-label">Name</label>
                <input type="text" id="name" name="name" class="form-control" placeholder="Enter your name" value="{{ old('name') }}" required>
            </div>

            <!-- Email Field -->
            <div class="form-group mb-3">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" id="email" name="email" class="form-control" placeholder="Enter your email" value="{{ old('email') }}" required>
            </div>

            <!-- Password Field -->
            <div class="form-group mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
            </div>

            <!-- Confirm Password Field -->
            <div class="form-group mb-4">
                <label for="password_confirmation" class="form-label">Confirm Password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" placeholder="Confirm your password" required>
            </div>

            <!-- Submit Button -->
            <div class="d-grid">
                <button type="submit" class="btn btn-primary btn-block">Register</button>
            </div>
        </form>
    </div>
</div>
@endsection
@section('styles')
<style>
    .card{
        box-shadow: black 10px 10px 20px;
        border-radius: 20px;
    }

    #backgroundCanvas {
        width: 100%;
        height: 100%;
    }
</style>
@endsection

@section('scripts')
<script>
    const canvas = document.getElementById('backgroundCanvas');
    const ctx = canvas.getContext('2d');

    // Set the canvas to be full-screen
    function resizeCanvas() {
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
    }
    window.addEventListener('resize', resizeCanvas);
    resizeCanvas();

    let waveOffset = 0;

    // Animate the wave
    function drawWaves() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        const waveHeight = 20; // Height of the wave
        const waveLength = 150; // Distance between peaks
        const waveSpeed = 0.05; // Speed of wave animation
        const numberOfWaves = 3; // How many wave layers
        const colors = ['#00bcd4', '#aabbaa', '#ffffff'];

        for (let i = 0; i < numberOfWaves; i++) {
            ctx.beginPath();
            ctx.moveTo(0, canvas.height / 2);

            for (let x = 0; x <= canvas.width; x += 10) {
                let y = Math.sin(x / waveLength + waveOffset + i) * waveHeight + canvas.height / 2 + i * 30;
                ctx.lineTo(x, y);
            }

            ctx.lineTo(canvas.width, canvas.height);
            ctx.lineTo(0, canvas.height);
            ctx.closePath();
            ctx.fillStyle = colors[i];
            ctx.globalAlpha = 0.6;
            ctx.fill();
        }

        waveOffset += waveSpeed; // Increase the wave offset to create the animation
        requestAnimationFrame(drawWaves);
    }

    // Start the wave animation
    drawWaves();
</script>
@endsection
