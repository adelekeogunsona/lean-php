# LeanPHP - Tiny, Fast REST API Microframework

A minimalist, high-performance PHP framework designed for building secure JSON REST APIs. LeanPHP focuses on simplicity, security, and speed while providing all the essential features needed for modern API development.

## ✨ Features

- **🚀 Fast & Lightweight**: Minimal overhead with OpCache optimization
- **🔒 Security First**: JWT authentication, rate limiting, CORS, input validation
- **📝 JSON-Only**: Stateless design, consistent error format (RFC 7807 Problem Details)
- **🛣️ Smart Routing**: Path parameters with constraints, route groups, middleware
- **⚡ High Performance**: Route caching, ETags, conditional requests
- **🔧 Developer Friendly**: Great error messages, debugging support
- **📊 Production Ready**: Logging, monitoring, error handling

## 🚀 Quick Start

### Prerequisites

- PHP 8.2+
- Composer
- Optional: APCu extension (for better rate limiting performance)

### Installation

```bash
# Clone the repository
git clone <your-repo-url> lean-php
cd lean-php

# Install dependencies
composer install

# Set up environment
cp .env.example .env
# Edit .env with your configuration

# Create Database
touch storage/database.sqlite

# Initialize database
php scripts/seed.php

# Start development server
php -S localhost:8000 -t public public/router.php
```

### Test the API

```bash
# Health check
curl http://localhost:8000/health

# Login to get a token
curl -X POST http://localhost:8000/login \
  -H "Content-Type: application/json" \
  -d '{"email":"demo@local","password":"secret"}'

# Use the token for authenticated requests
curl http://localhost:8000/v1/users \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

## 🧪 Testing

```bash
# Run all tests
composer test

# Run specific test file
composer test tests/Unit/AuthTest.php

# Run static analysis
composer stan

# Generate route cache for production
php bin/route_cache.php
```

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 🤝 Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📞 Support

[View the full documentation](docs/README.md)
- 🐛 Issues: Open an issue on GitHub
- 💡 Feature Requests: Open a discussion on GitHub

---

**Built with ❤️ by Adeleke**
