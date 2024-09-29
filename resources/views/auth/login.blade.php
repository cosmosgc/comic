@extends('layouts.app')

@section('title', 'Login')

@section('content')
<canvas id="canvas" class="canvas" style="position:absolute; top:0; left:0; z-index:-1;"></canvas>
<svg xmlns="http://www.w3.org/2000/svg" version="1.1" style="
    position: absolute;
"   >
  <defs>
  <filter id="liquid">
      <!-- Apply Gaussian Blur -->
      <feGaussianBlur in="SourceGraphic" stdDeviation="9" result="blur" />

      <!-- Generate turbulence (noise) for displacement -->
      <!-- <feTurbulence type="fractalNoise" baseFrequency="0.02" numOctaves="1" result="turbulence" /> -->

      <!-- Use displacement map to create the liquid-like effect -->
      <feDisplacementMap in2="turbulence" in="blur" scale="30" xChannelSelector="B" yChannelSelector="G" />
    </filter>
  </defs>
</svg>
<div class="container d-flex justify-content-center align-items-center" style="min-height: 100vh;">
    <div class="card bg-dark text-light p-4" style="width: 100%; max-width: 400px; box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);">
        <h3 class="text-center mb-4">Login</h3>
        <form method="POST" action="{{ route('login') }}">
            @csrf

            <!-- Email Field -->
            <div class="form-group mb-3">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" id="email" name="email" class="form-control" placeholder="Enter your email" required>
            </div>

            <!-- Password Field -->
            <div class="form-group mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required>
            </div>

            <!-- Submit Button -->
            <div class="d-grid">
                <button type="submit" class="btn btn-primary btn-block" style="background-color: #007bff; border: none; padding: 10px 15px; font-size: 16px;">
                    Login
                </button>
            </div>

            <!-- Forgot Password and Register Links -->
            <div class="text-center mt-3">
                <a href="{{ route('password.request') }}">Forgot Password?</a>
                <br>
                <a href="{{ route('register') }}">Don't have an account? Sign up</a>
            </div>
        </form>
    </div>
</div>
@endsection

@section('styles')
<style>
    .canvas{
        background-color: #272c31;
    }
    #canvas{
        position: absolute;
    }
    .custom-root-container{
        overflow: hidden;
    }


</style>
@endsection



@section('scripts')
<script>
    // Setup canvas and context
    const canvas = document.getElementById('canvas');
    const ctx = canvas.getContext('2d');
    let width = window.innerWidth;
    let height = window.innerHeight;
    canvas.width = width;
    canvas.height = height;

    let waveOffset = 0; // Variable to animate the wave's movement

    const waveHeight = 20; // Height of the wave
    const waveLength = 150; // Distance between peaks
    const waveSpeed = 0.05; // Speed of wave animation
    const numberOfWaves = 12; // How many wave layers
    const colors = ['#ffbcd4', '#aabbaa', '#dd9999', '#aa9999', '#ffffff', '#aaaaaa', '#dddddd', '#eeeeee', '#cccccc', '#999999','#aa9999', '#ffffff']; // Colors for each wave layer

    function drawWaves() {
        ctx.clearRect(0, 0, canvas.width, canvas.height); // Clear the canvas before redrawing

        for (let i = 0; i < numberOfWaves; i++) {
            ctx.beginPath();
            ctx.moveTo(0, canvas.height / 2);

            // Loop over each x-axis point
            for (let x = 0; x <= canvas.width; x += 10) {
                let y = Math.sin(x / waveLength + waveOffset + i) * waveHeight + canvas.height / 2 + i * 30;
                ctx.lineTo(x, y);
            }

            // Fill the wave
            ctx.lineTo(canvas.width, canvas.height);
            ctx.lineTo(0, canvas.height);
            ctx.closePath();
            ctx.fillStyle = colors[i];
            ctx.globalAlpha = 0.6; // Apply transparency
            ctx.fill();
        }

        waveOffset += waveSpeed; // Update wave offset for animation

        drawCircles(); // Draw circles after waves

        requestAnimationFrame(drawWaves); // Animate
    }

    const circles = [];

    function createCircles() {
        for (let i = 0; i < 15; i++) {
            circles.push({
                x: Math.random() * width,
                y: Math.random() * height,
                radius: Math.random() * 20 + 5,
                dx: (Math.random() - 0.5) * 2,
                dy: (Math.random() - 0.5) * 2
            });
        }
    }

    function drawCircles() {
        circles.forEach(circle => {
            ctx.beginPath();
            ctx.arc(circle.x, circle.y, circle.radius, 0, Math.PI * 2);
            ctx.fillStyle = 'rgba(255, 255, 255, 0.2)';
            ctx.fill();

            // Move circles
            circle.x += circle.dx;
            circle.y += circle.dy;

            // Bounce off walls
            if (circle.x + circle.radius > width || circle.x - circle.radius < 0) {
                circle.dx = -circle.dx;
            }
            if (circle.y + circle.radius > height || circle.y - circle.radius < 0) {
                circle.dy = -circle.dy;
            }
        });
    }

    function resizeCanvas() {
        width = window.innerWidth;
        height = window.innerHeight;
        canvas.width = width;
        canvas.height = height;
    }

    window.addEventListener('resize', resizeCanvas);
    createCircles();
    drawWaves();
</script>
@endsection
