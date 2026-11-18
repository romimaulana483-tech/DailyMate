<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="DailyMate – Your Smart Everyday Assistant. Manage tasks, plan schedules, track productivity and get AI-powered daily planning.">
    <title>DailyMate – Your Smart Everyday Assistant</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <!-- ============================ Navbar ============================ -->
    <nav class="navbar navbar-expand-lg landing-nav">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="{{ route('home') }}">
                <span class="brand-icon"><i class="bi bi-check2-square"></i></span>
                <span>Daily<span class="text-gradient">Mate</span></span>
            </a>

            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#landingNav" aria-controls="landingNav" aria-expanded="false" aria-label="Toggle navigation">
                <i class="bi bi-list fs-2 text-dark"></i>
            </button>

            <div class="collapse navbar-collapse" id="landingNav">
                <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="#about">About</a></li>
                    <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
                    <li class="nav-item"><a class="nav-link" href="#how-it-works">How It Works</a></li>
                </ul>
                <div class="d-flex flex-column flex-lg-row gap-2 align-items-lg-center">
                    @auth
                        <a href="{{ auth()->user()->isAdmin() ? route('admin.dashboard') : route('user.dashboard') }}" class="btn btn-primary">
                            <i class="bi bi-grid-1x2 me-1"></i> Open Dashboard
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="btn btn-light">Sign in</a>
                        <a href="{{ route('register') }}" class="btn btn-primary">Get Started</a>
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    <!-- ============================ Hero ============================ -->
    <section class="landing-hero">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-6">
                    <span class="hero-badge">
                        <i class="bi bi-stars"></i> Powered by an AI productivity assistant
                    </span>
                    <h1>Your Smart <br><span class="gradient-text">Everyday Assistant</span></h1>
                    <p class="lead">
                        DailyMate brings your tasks, schedules, priorities and productivity insights together in one
                        calm workspace — with an AI assistant that helps you decide what to do next.
                    </p>
                    <div class="hero-cta">
                        @auth
                            <a href="{{ auth()->user()->isAdmin() ? route('admin.dashboard') : route('user.dashboard') }}" class="btn btn-primary btn-lg">
                                <i class="bi bi-grid-1x2 me-1"></i> Go to Dashboard
                            </a>
                        @else
                            <a href="{{ route('register') }}" class="btn btn-primary btn-lg">
                                Get Started Free <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                            <a href="{{ route('login') }}" class="btn btn-light btn-lg">Sign in</a>
                        @endauth
                    </div>
                    <div class="hero-trust">
                        <span><i class="bi bi-check-circle-fill"></i> Free to start</span>
                        <span><i class="bi bi-check-circle-fill"></i> Private by design</span>
                        <span><i class="bi bi-check-circle-fill"></i> Works on any device</span>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="dashboard-mockup">
                        <div class="mockup-bar"><span></span><span></span><span></span></div>
                        <div class="mockup-body">
                            <div class="mockup-side">
                                <span></span><span class="active"></span><span></span><span></span><span></span>
                            </div>
                            <div class="mockup-main">
                                <div class="mockup-hero">
                                    <strong>Good morning, Alya 👋</strong>
                                    <small>You have 4 tasks and 2 schedules today.</small>
                                    <div class="bar"></div>
                                </div>
                                <div class="mockup-stats">
                                    <div class="mockup-stat"><small>Total</small><strong>24</strong></div>
                                    <div class="mockup-stat"><small>Done</small><strong>17</strong></div>
                                    <div class="mockup-stat"><small>Rate</small><strong>71%</strong></div>
                                </div>
                                <div class="mockup-chart">
                                    <div class="bar" style="height: 35%"></div>
                                    <div class="bar" style="height: 55%"></div>
                                    <div class="bar" style="height: 42%"></div>
                                    <div class="bar" style="height: 78%"></div>
                                    <div class="bar" style="height: 62%"></div>
                                    <div class="bar" style="height: 92%"></div>
                                    <div class="bar" style="height: 70%"></div>
                                </div>
                            </div>
                        </div>
                        <div class="floating-card top">
                            <span class="fc-icon"><i class="bi bi-check-lg"></i></span>
                            <div><strong>Task completed</strong><small>Just now</small></div>
                        </div>
                        <div class="floating-card bottom">
                            <span class="fc-icon"><i class="bi bi-stars"></i></span>
                            <div><strong>AI daily plan ready</strong><small>3 priorities for today</small></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================ About ============================ -->
    <section class="landing-section" id="about">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-5">
                    <div class="section-eyebrow">About DailyMate</div>
                    <h2 class="section-heading">A simpler way to own your day</h2>
                    <p class="section-sub mb-4">
                        DailyMate is a personal productivity workspace built for students, professionals and anyone
                        juggling a busy schedule. Instead of scattering plans across notes and apps, you get one
                        clear view of what matters now.
                    </p>
                    <div class="d-flex flex-column gap-3">
                        <div class="d-flex gap-3">
                            <span class="stat-icon primary"><i class="bi bi-bullseye"></i></span>
                            <div>
                                <div class="fw-700">Clarity over clutter</div>
                                <div class="fs-13 text-muted">Priorities, deadlines and schedules in one focused dashboard.</div>
                            </div>
                        </div>
                        <div class="d-flex gap-3">
                            <span class="stat-icon violet"><i class="bi bi-shield-check"></i></span>
                            <div>
                                <div class="fw-700">Private by design</div>
                                <div class="fs-13 text-muted">Your tasks, schedules and conversations are only visible to you.</div>
                            </div>
                        </div>
                        <div class="d-flex gap-3">
                            <span class="stat-icon success"><i class="bi bi-graph-up-arrow"></i></span>
                            <div>
                                <div class="fw-700">Progress you can see</div>
                                <div class="fs-13 text-muted">Real statistics help you understand your productivity habits.</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <div class="feature-card">
                                <div class="feature-icon"><i class="bi bi-check2-square"></i></div>
                                <h5>One source of truth</h5>
                                <p>Every task, deadline and note lives in a single organised list you can filter in seconds.</p>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="feature-card">
                                <div class="feature-icon violet"><i class="bi bi-calendar3"></i></div>
                                <h5>Plan with confidence</h5>
                                <p>See your month at a glance so appointments, classes and focus time never collide.</p>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="feature-card">
                                <div class="feature-icon success"><i class="bi bi-bar-chart-line"></i></div>
                                <h5>Measure what matters</h5>
                                <p>Completion rate, priority mix and daily progress charts show how you are really doing.</p>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="feature-card">
                                <div class="feature-icon warning"><i class="bi bi-stars"></i></div>
                                <h5>An assistant that helps</h5>
                                <p>Ask for a daily plan, a priority check or a motivation boost whenever you feel stuck.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================ Features ============================ -->
    <section class="landing-section alt" id="features">
        <div class="container">
            <div class="text-center mb-5">
                <div class="section-eyebrow">Features</div>
                <h2 class="section-heading">Everything you need for a productive day</h2>
                <p class="section-sub mx-auto">Four connected tools that remove the friction between planning and doing.</p>
            </div>

            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon"><i class="bi bi-check2-square"></i></div>
                        <h5>Task Management</h5>
                        <p>Create, prioritise and complete tasks with clear status, category and deadline tracking.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon violet"><i class="bi bi-clock-history"></i></div>
                        <h5>Schedule Management</h5>
                        <p>Plan appointments and activities by date and time, then see everything on a monthly calendar.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon success"><i class="bi bi-bar-chart-line"></i></div>
                        <h5>Productivity Statistics</h5>
                        <p>Understand your completion rate and priority distribution through clean, interactive charts.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon warning"><i class="bi bi-stars"></i></div>
                        <h5>AI Assistant</h5>
                        <p>Get practical advice, daily plans and motivation from an assistant that understands your workload.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================ How it works ============================ -->
    <section class="landing-section" id="how-it-works">
        <div class="container">
            <div class="text-center mb-5">
                <div class="section-eyebrow">How It Works</div>
                <h2 class="section-heading">From busy to balanced in three steps</h2>
                <p class="section-sub mx-auto">No complicated setup. Create an account and start organising immediately.</p>
            </div>

            <div class="row g-4">
                <div class="col-md-4">
                    <div class="step-card">
                        <div class="step-number">1</div>
                        <h5>Create your account</h5>
                        <p>Sign up with your email in under a minute. Your personal workspace is ready instantly.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="step-card">
                        <div class="step-number">2</div>
                        <h5>Plan tasks &amp; schedule</h5>
                        <p>Add what needs to get done, set priorities and deadlines, then block time for what matters.</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="step-card">
                        <div class="step-number">3</div>
                        <h5>Track, learn, improve</h5>
                        <p>Complete tasks, follow your statistics and ask the AI assistant to help refine your routine.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============================ CTA ============================ -->
    <section class="cta-section">
        <div class="container text-center position-relative">
            <h2>Ready to make every day count?</h2>
            <p>Join DailyMate and turn a long to-do list into a clear, achievable plan — starting today.</p>
            @auth
                <a href="{{ auth()->user()->isAdmin() ? route('admin.dashboard') : route('user.dashboard') }}" class="btn btn-light btn-lg">
                    Open my dashboard <i class="bi bi-arrow-right ms-1"></i>
                </a>
            @else
                <a href="{{ route('register') }}" class="btn btn-light btn-lg">
                    Get Started Free <i class="bi bi-arrow-right ms-1"></i>
                </a>
            @endauth
        </div>
    </section>

    <!-- ============================ Footer ============================ -->
    <footer class="landing-footer">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-5">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="brand-icon"><i class="bi bi-check2-square"></i></span>
                        <span class="footer-brand">Daily<span class="text-gradient">Mate</span></span>
                    </div>
                    <p class="fs-13 mb-0" style="max-width: 340px;">
                        Your smart everyday assistant for tasks, schedules, productivity and AI-powered planning.
                        Built to help you do less busywork and more meaningful work.
                    </p>
                </div>
                <div class="col-6 col-lg-2 offset-lg-1">
                    <h6>Product</h6>
                    <a href="#about">About</a>
                    <a href="#features">Features</a>
                    <a href="#how-it-works">How It Works</a>
                </div>
                <div class="col-6 col-lg-2">
                    <h6>Account</h6>
                    @auth
                        <a href="{{ auth()->user()->isAdmin() ? route('admin.dashboard') : route('user.dashboard') }}">Dashboard</a>
                        <a href="{{ auth()->user()->isAdmin() ? route('admin.profile.edit') : route('user.profile.edit') }}">Profile</a>
                    @else
                        <a href="{{ route('login') }}">Sign in</a>
                        <a href="{{ route('register') }}">Create account</a>
                    @endauth
                </div>
                <div class="col-6 col-lg-2">
                    <h6>DailyMate</h6>
                    <a href="#features">Task Management</a>
                    <a href="#features">AI Assistant</a>
                    <a href="#features">Statistics</a>
                </div>
            </div>
            <div class="footer-bottom d-flex flex-wrap justify-content-between gap-2">
                <span>&copy; {{ date('Y') }} DailyMate. All rights reserved.</span>
                <span>Your Smart Everyday Assistant.</span>
            </div>
        </div>
    </footer>

    <script src="{{ asset('vendor/bootstrap.bundle.min.js') }}"></script>
</body>
</html>
