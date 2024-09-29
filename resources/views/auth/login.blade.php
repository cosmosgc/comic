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
        background-color: #000;
    }
    #canvas{
        position: absolute;
        filter: url("#liquid");
    }
    .custom-root-container{
        overflow: hidden;
    }


</style>
@endsection

@section('scripts')
<script>
const canvas = document.getElementById("canvas"),
  context = canvas.getContext("2d"),
  colorPallete =
      ["#f0f8ff", "#e6eaed", "#bbbfc1", "#e2e4e5", "#f4f9fb", "#ffffff"];
    //   ["#00f", "#00a", "#00b", "#00c", "#00d", "#00e"];
// ["#f00","#a00","#b00","#c00","#d00","#e00"];
// ["white","#888","yellow","orange","darkorange","darkmagenta","darkgreen","khaki"];

var width = canvas.width = window.innerWidth,
  height = canvas.height = window.innerHeight,
  src = {
    x: width / 2,
    y: height / 3
  },
  circles = [];

window.onresize = function() {
  width = canvas.width = window.innerWidth;
  height = canvas.height = window.innerHeight;
  src.x= width / 2;
  src.y= height / 2;
}

class Circle {
  constructor() {
    this.x = src.x;
    this.y = src.y;
    this.angle = Math.PI * 2 * Math.random();
    var speed=1 + Math.random();
    this.vx = speed* Math.cos(this.angle);
    this.vy = speed* Math.sin(this.angle);

    // this.xr = 6 + 10 * Math.random();
    // this.yr = 2 + 10 * Math.random();
    this.r = 6 + 10 * Math.random()

    this.color = colorPallete[Math.floor(Math.random() * colorPallete.length)];
  }

  update() {
    this.x += this.vx;
    this.y += this.vy;

    // this.xr-= .01;
    // this.yr -= .01;
    // this.r=Math.min(this.yr,this.xr);
    this.r -= .01;

  }
}

function removeCircles() {
 circles = circles.filter(
    (b) =>
      !(
        b.x + b.r < 0 ||
        b.x - b.r > width ||
        b.y + b.r < 0 ||
        b.y - b.r > height ||
        b.r < 0
      )
  );
}

function renderCircles() {
  context.clearRect(0, 0, width, height);

  if (Math.random() > .2)
    circles.push(new Circle());

  for (var i = 0; i < circles.length; i++) {
    var b = circles[i];
    context.fillStyle = b.color;
    context.beginPath();

    context.arc(b.x, b.y, b.r, 0, Math.PI * 2, false);
    // context.ellipse(b.x, b.y, b.xr, b.yr, b.angle, 0, 2 * Math.PI);

    context.fill();
    b.update();
  }

  removeCircles();
  requestAnimationFrame(renderCircles);
}

renderCircles();

// https://codepen.io/mnmxmx/pen/VjjvEq
</script>
@endsection

