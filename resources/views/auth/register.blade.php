<form method="POST" action="{{ route('register') }}">
    @csrf
    <label>Name</label>
    <input type="text" name="name" required>
    <label>Email</label>
    <input type="email" name="email" required>
    <label>Password</label>
    <input type="password" name="password" required>
    <label>Confirm Password</label>
    <input type="password" name="password_confirmation" required>
    <button type="submit">Register</button>
</form>
