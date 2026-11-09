<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Securely sign in to your school's SchoolPilot workspace.">
    <title>School Login | SchoolPilot</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color: #172b43; background: radial-gradient(circle at 15% 10%, #d9f5f0 0, transparent 30%), linear-gradient(135deg, #f7fafc, #edf4fa); }
        .shell { width: min(100%, 440px); }
        .brand { display: flex; justify-content: center; align-items: center; gap: 10px; margin-bottom: 24px; color: #10243d; font-size: 1.35rem; font-weight: 850; letter-spacing: -.04em; text-decoration: none; }
        .mark { width: 40px; height: 40px; display: grid; place-items: center; border-radius: 12px; color: white; background: linear-gradient(135deg, #1768a8, #12a89d); }
        .card { padding: 36px; border: 1px solid #e3ebf2; border-radius: 20px; background: white; box-shadow: 0 24px 65px #183b5a18; }
        h1 { margin: 0; color: #10243d; text-align: center; font-size: 1.8rem; letter-spacing: -.045em; }
        .intro { margin: 10px 0 28px; color: #64748b; text-align: center; line-height: 1.6; }
        label { display: block; margin: 17px 0 7px; color: #34465b; font-size: .9rem; font-weight: 700; }
        input { width: 100%; padding: 13px 14px; border: 1px solid #d6e0e9; border-radius: 10px; outline: none; font: inherit; }
        input:focus { border-color: #1768a8; box-shadow: 0 0 0 3px #1768a81a; }
        .remember { display: flex; align-items: center; gap: 9px; margin: 17px 0 22px; color: #64748b; font-size: .9rem; }
        .remember input { width: auto; }
        button { width: 100%; padding: 14px; border: 0; border-radius: 10px; color: white; background: #1768a8; font: inherit; font-weight: 750; cursor: pointer; }
        button:hover { background: #125889; }
        .error { padding: 11px 13px; border-radius: 9px; color: #a12727; background: #fff0f0; font-size: .9rem; }
        .back { display: block; margin-top: 22px; color: #1768a8; text-align: center; font-size: .9rem; font-weight: 700; text-decoration: none; }
        @media(max-width:480px) { .card { padding: 27px 22px; } }
    </style>
</head>
<body>
    <main class="shell">
        <a class="brand" href="/"><span class="mark">S</span>SchoolPilot</a>
        <section class="card">
            <h1>Welcome back</h1>
            <p class="intro">Sign in with your school email and password. We'll take you to your school's workspace.</p>

            @if($errors->any())
                <div class="error" role="alert">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="/login">
                @csrf
                <label for="email">School email address</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="you@school.edu" autocomplete="username" required autofocus>
                <label for="password">Password</label>
                <input id="password" type="password" name="password" placeholder="Enter your password" autocomplete="current-password" required>
                <label class="remember"><input type="checkbox" name="remember" value="1"> Keep me signed in</label>
                <button type="submit">Sign in to SchoolPilot</button>
            </form>
        </section>
        <a class="back" href="/">← Back to SchoolPilot homepage</a>
    </main>
</body>
</html>