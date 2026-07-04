OVERVIEW
TourVolt is a private command center for solo tour operators, replacing notebooks with a secure, premium system.
It centralises trips, clients, payments, itineraries, resources, and profit — all wrapped in a cinematic, brand‑aligned interface.

"Trip operations, in one place."

Designed for African Breathtaking Adventure, TourVolt is built to scale (multi‑tenant schema) but remains focused on one operator's daily workflow.

KEY FEATURES.
Module	What It Does
 Authentication	Secure login with bcrypt, session management, and optional "Remember me" (30‑day token).
  Dashboard	At‑a‑glance metrics: outstanding balance, monthly revenue, profit, and upcoming trips with weekly comparison.
 Trips	Full CRUD, status filter, urgency badges, inline status change, quick add payment/cost, and CSV export.
 Trip Detail	Workspace with itinerary builder, cost lines, payments, resource assignment, and live profit calculation.
 Money	Financial overview: outstanding per trip, total revenue, costs, net profit, and charts for top outstanding.
 Clients	CRM‑style list with search, active badges, total trips, revenue, and clickable names to view full profile.
 Client Profile	Complete client history: contact info, all trips, revenue, profit, and quick "New Trip" action.
 Quote / PDF	Branded, printable quote with itinerary, cost breakdown, and professional layout.
 Premium UI	Dark/light mode, glass‑morphism, responsive design, and rich typography (Fraunces + Inter).

 TECH STACK
Layer	Technology
Backend	PHP 7.4+ (PDO, sessions, bcrypt)
Database	MariaDB / MySQL
Frontend	HTML5, Tailwind CSS (custom build), Chart.js, Tabler Icons
Authentication	Session‑based + secure HTTP‑only cookie for "Remember me"
Hosting	Any PHP/MySQL shared hosting or VPS (Apache / Nginx)

PROJECT STRUCTURE
text
tourvolt/
├── assets/
│   ├── css/
│   │   └── app.css              # Tailwind-based design system
│   └── js/
│       └── theme.js             # Dark mode toggle + interactions
├── includes/
│   ├── auth.php                 # Session handling, login, remember‑me logic
│   ├── config.php               # DB credentials + PDO connection
│   └── functions.php            # Helpers (badges, dates, etc.)
├── partials/
│   ├── header.php               # Nav, dark mode toggle
│   └── footer.php               # Closing tags + scripts
├── login.php                    # Public login page
├── dashboard.php                # Home screen
├── trips.php                    # Trip list with filters & inline actions
├── trip_detail.php              # Full trip workspace
├── clients.php                  # Client CRM
├── client_profile.php           # Individual client profile
├── money.php                    # Financial dashboard
├── quote.php                    # Quote preview + print
├── logout.php                   # Logout handler
└── index.php                    # Redirect to dashboard
