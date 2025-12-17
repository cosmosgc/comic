@extends('layouts.app')

@section('title', 'Login')

@section('content')

<!-- Background canvas -->
<!-- Background canvas -->
<canvas
    id="canvas"
    class="fixed inset-0 z-0 block"></canvas>

<!-- SVG filter (must not affect layout) -->
<svg xmlns="http://www.w3.org/2000/svg"
     class="fixed inset-0 z-0 pointer-events-none">
    <defs>
        <filter id="liquid">
            <feGaussianBlur in="SourceGraphic" stdDeviation="9" result="blur" />
            <feDisplacementMap in="blur" scale="30"
                               xChannelSelector="B"
                               yChannelSelector="G" />
        </filter>
    </defs>
</svg>


<div class="relative z-10 flex min-h-screen items-center justify-center px-4">
    <div
        class="w-full max-w-md rounded-2xl border border-zinc-800 bg-zinc-900/90 p-6 shadow-xl backdrop-blur">

        <h1 class="mb-6 text-center text-2xl font-bold tracking-tight">
            Login
        </h1>

        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf

            <!-- Email -->
            <div>
                <label for="email" class="mb-1 block text-sm font-medium text-zinc-300">
                    Email address
                </label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    required
                    placeholder="you@example.com"
                    class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm
                           focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30"
                >
            </div>

            <!-- Password -->
            <div>
                <label for="password" class="mb-1 block text-sm font-medium text-zinc-300">
                    Password
                </label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                    placeholder="••••••••"
                    class="w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm
                           focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30"
                >
            </div>

            <!-- Submit -->
            <button
                type="submit"
                class="mt-4 w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white
                       transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/40">
                Login
            </button>

            <!-- Links -->
            <div class="pt-4 text-center text-sm text-zinc-400">
                <a href="{{ route('password.request') }}"
                   class="hover:text-white underline-offset-4 hover:underline">
                    Forgot password?
                </a>
                <div class="mt-2">
                    <a href="{{ route('register') }}"
                       class="hover:text-white underline-offset-4 hover:underline">
                        Don’t have an account? Sign up
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

@endsection


@section('styles')
<style>
    html, body {
        width: 100%;
        height: 100%;
    }

    #canvas {
        width: 100vw;
        height: 100vh;
        background-color: #272c31;
        display: block;
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
