# Contributing to UpcycleConnect

Thank you for considering contributing to UpcycleConnect. This document provides guidelines and instructions for contributing to the project.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [Development Workflow](#development-workflow)
- [Coding Standards](#coding-standards)
- [Testing](#testing)
- [Pull Request Process](#pull-request-process)
- [Reporting Bugs](#reporting-bugs)
- [Feature Requests](#feature-requests)

## Code of Conduct

### Our Pledge

We are committed to providing a welcoming and inclusive environment for all contributors, regardless of experience level, gender, gender identity and expression, sexual orientation, disability, personal appearance, body size, race, ethnicity, age, religion, or nationality.

### Our Standards

**Positive behavior includes**:

- Using welcoming and inclusive language
- Being respectful of differing viewpoints
- Gracefully accepting constructive criticism
- Focusing on what is best for the community
- Showing empathy towards others

**Unacceptable behavior includes**:

- Harassment or discriminatory language
- Trolling or insulting comments
- Personal or political attacks
- Publishing others' private information
- Other conduct inappropriate in a professional setting

## Getting Started

### Prerequisites

Before contributing, ensure you have:

1. **Development Environment**:
   - PHP 7.4+
   - Go 1.25+
   - MySQL 8.0+
   - Composer
   - Git

2. **Knowledge**:
   - Familiarity with PHP and Go
   - Understanding of RESTful APIs
   - Basic SQL knowledge
   - Git workflow experience

### Repository Setup

1. **Fork the repository** on GitHub
2. **Clone your fork**:

   ```bash
   git clone https://github.com/YOUR_USERNAME/UpcycleConnect.git
   cd UpcycleConnect
   ```

3. **Add upstream remote**:

   ```bash
   git remote add upstream https://github.com/labarbe-leo-gits/UpcycleConnect.git
   ```

4. **Install dependencies**:

   ```bash
   cd "PA - Site Principal"
   composer install

   cd "../PA - API"
   go mod download
   ```

5. **Configure environment**:
   - Copy `.env.example` to `.env` in both `PA - Site Principal` and
     `PA - API` directories.
   - Populate the variables with appropriate values (database credentials,
     API keys, redirect URIs, etc.).
   - Do **not** commit the resulting `.env` files.
   - See [docs/SETUP.md](../docs/SETUP.md) for a complete list of variables and
     additional instructions.

6. **Start services and verify**:
   - Ensure MySQL and Apache are running (XAMPP control panel or system
     services).
   - In one terminal run the API:

     ```bash
     cd "PA - API"
     go run .
     ```

   - Open the frontend in a browser:

     ```text
     http://localhost/PA/PA - Site Principal/pages/public/index.php
     ```

   - If the application loads and you can register/log in, the setup is
     complete.

## Development Workflow

### Branch Strategy

We use the following branch structure:

- `main` - Production-ready code
- `develop` - Integration branch for features
- `feature/feature-name` - New features
- `bugfix/bug-name` - Bug fixes
- `hotfix/fix-name` - Critical production fixes

### Creating a Feature Branch

```bash
# Update your local repository
git checkout main
git pull upstream main

# Create feature branch
git checkout -b feature/your-feature-name
```

### Making Changes

1. **Write clean, documented code** following our coding standards
2. **Test your changes** thoroughly
3. **Commit frequently** with clear messages
4. **Keep commits atomic** (one logical change per commit)

### Commit Message Format

We follow the Conventional Commits specification:

```
<type>(<scope>): <subject>

<body>

<footer>
```

**Types**:

- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `style`: Code style changes (formatting, no logic change)
- `refactor`: Code refactoring
- `test`: Adding or updating tests
- `chore`: Maintenance tasks

**Example**:

```
feat(auth): add Google OAuth support

Implemented Google OAuth 2.0 authentication flow:
- Added OAuth callback handler
- Updated user model to support OAuth fields
- Created configuration for Google credentials

Closes #123
```

### Syncing with Upstream

Regularly sync your fork with the upstream repository:

```bash
git fetch upstream
git checkout main
git merge upstream/main
git push origin main
```

## Coding Standards

### PHP Standards

Follow PSR-12 coding standard:

```php
<?php

declare(strict_types=1);

namespace App\Services;

class UserService
{
    private $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function getUserById(string $id): ?User
    {
        // Implementation
    }
}
```

**Key points**:

- Use strict typing where possible
- Follow PSR-4 autoloading
- Use meaningful variable names
- Add PHPDoc comments for complex functions
- Avoid global variables

### Go Standards

Follow Go standard formatting:

```go
package app

import (
    "API/models"
    "database/sql"
    "errors"
)

// GetUserByID retrieves a user by their ID
func GetUserByID(db *sql.DB, id string) (*models.User, error) {
    if id == "" {
        return nil, errors.New("id cannot be empty")
    }

    var user models.User
    // Implementation

    return &user, nil
}
```

**Key points**:

- Run `gofmt` before committing
- Use meaningful package names
- Export only necessary functions
- Add comments for exported functions
- Handle errors explicitly

### SQL Standards

```sql
-- Use uppercase for SQL keywords
SELECT
    u.id,
    u.username,
    u.email
FROM users u
WHERE u.active = 1
ORDER BY u.created_at DESC;

-- Use meaningful aliases
-- Indent for readability
-- Comment complex queries
```

### JavaScript Standards

```javascript
// Use const/let, avoid var
const API_URL = "http://localhost:9999";

// Use arrow functions where appropriate
const fetchUser = async (userId) => {
  const response = await fetch(`${API_URL}/users/${userId}`);
  return response.json();
};

// Add JSDoc comments
/**
 * Validates email format
 * @param {string} email - Email address to validate
 * @returns {boolean} True if valid
 */
function isValidEmail(email) {
  const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return regex.test(email);
}
```

### CSS Standards

```css
/* Use BEM naming convention */
.login-form {
  max-width: 400px;
  margin: 0 auto;
}

.login-form__input {
  width: 100%;
  padding: 10px;
}

.login-form__button {
  background-color: #007bff;
  color: white;
}

.login-form__button--disabled {
  opacity: 0.5;
}

/* Group related properties */
/* Use meaningful class names */
/* Avoid deep nesting */
```

### File Organization

```
PA - Site Principal/
├── assets/           # Static files
│   ├── css/          # Stylesheets
│   ├── js/           # JavaScript
│   └── img/          # Images
├── config/           # Configuration
├── includes/         # Reusable PHP
├── pages/            # Application pages
└── vendor/           # Dependencies

PA - API/
├── app/              # Handlers
├── db/               # Database layer
├── models/           # Data models
└── utils/            # Utilities
```

## Testing

### PHP Testing

Currently manual testing is used. Future implementation will include:

```php
// Example PHPUnit test
class UserServiceTest extends TestCase
{
    public function testUserCreation()
    {
        $user = new User();
        $user->setUsername('testuser');

        $this->assertEquals('testuser', $user->getUsername());
    }
}
```

### Go Testing

Write tests for new functionality:

```go
// user_test.go
package app

import "testing"

func TestValidateUserDto(t *testing.T) {
    tests := []struct {
        name    string
        user    models.User
        wantErr bool
    }{
        {
            name: "valid user",
            user: models.User{
                Username: "testuser",
                Email:    "test@example.com",
                Password: "password123",
            },
            wantErr: false,
        },
        {
            name: "invalid email",
            user: models.User{
                Username: "testuser",
                Email:    "invalid-email",
                Password: "password123",
            },
            wantErr: true,
        },
    }

    for _, tt := range tests {
        t.Run(tt.name, func(t *testing.T) {
            err := ValidateUserDto(tt.user)
            if (err != nil) != tt.wantErr {
                t.Errorf("ValidateUserDto() error = %v, wantErr %v", err, tt.wantErr)
            }
        })
    }
}
```

Run tests:

```bash
go test ./...
```

### Manual Testing Checklist

Before submitting PR, verify:

- [ ] Feature works as expected
- [ ] No console errors
- [ ] Mobile responsive
- [ ] Cross-browser compatible (Chrome, Firefox, Safari)
- [ ] No security vulnerabilities introduced
- [ ] Database migrations work correctly
- [ ] API endpoints return correct status codes
- [ ] Error handling works properly

## Pull Request Process

### Before Submitting

1. **Ensure code quality**:

   ```bash
   # PHP
   composer run-script cs-check  # If code sniffer is configured

   # Go
   go fmt ./...
   go vet ./...
   ```

2. **Update documentation** if needed
3. **Add tests** for new functionality
4. **Update CHANGELOG** (if applicable)

### Submitting Pull Request

1. **Push to your fork**:

   ```bash
   git push origin feature/your-feature-name
   ```

2. **Create PR** on GitHub:
   - Use descriptive title
   - Reference related issues
   - Provide detailed description
   - Add screenshots for UI changes

3. **PR template**:

   ```markdown
   ## Description

   Brief description of changes

   ## Type of Change

   - [ ] Bug fix
   - [ ] New feature
   - [ ] Breaking change
   - [ ] Documentation update

   ## Related Issues

   Closes #123

   ## Testing

   Describe testing performed

   ## Screenshots

   (If applicable)

   ## Checklist

   - [ ] Code follows project standards
   - [ ] Self-reviewed code
   - [ ] Commented complex code
   - [ ] Updated documentation
   - [ ] No new warnings
   - [ ] Added tests
   - [ ] All tests pass
   ```

### Review Process

1. **Maintainer review** - Code review by project maintainers
2. **Automated checks** - CI/CD pipeline (future)
3. **Feedback addressed** - Make requested changes
4. **Approval** - PR approved by maintainer
5. **Merge** - PR merged to appropriate branch

### After Merge

1. **Delete branch**:

   ```bash
   git branch -d feature/your-feature-name
   git push origin --delete feature/your-feature-name
   ```

2. **Update local main**:
   ```bash
   git checkout main
   git pull upstream main
   ```

## Reporting Bugs

### Before Reporting

1. **Search existing issues** to avoid duplicates
2. **Verify bug** in latest version
3. **Gather information**:
   - Steps to reproduce
   - Expected behavior
   - Actual behavior
   - Environment details

### Bug Report Template

```markdown
## Bug Description

Clear description of the bug

## Steps to Reproduce

1. Go to '...'
2. Click on '...'
3. Scroll down to '...'
4. See error

## Expected Behavior

What should happen

## Actual Behavior

What actually happens

## Screenshots

(If applicable)

## Environment

- OS: [e.g., Windows 10]
- Browser: [e.g., Chrome 118]
- PHP Version: [e.g., 7.4.33]
- Go Version: [e.g., 1.25.2]
- MySQL Version: [e.g., 8.0.35]

## Additional Context

Any other relevant information
```

## Feature Requests

### Proposing Features

1. **Check existing requests** to avoid duplicates
2. **Describe the feature** clearly
3. **Explain the use case**
4. **Provide examples** if applicable

### Feature Request Template

```markdown
## Feature Description

Clear description of proposed feature

## Problem It Solves

What problem does this address?

## Proposed Solution

How should this work?

## Alternatives Considered

Other approaches you've considered

## Additional Context

Mockups, examples, references
```

## Development Tips

### Debugging

**PHP**:

```php
// Enable error reporting in development
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Use var_dump for debugging
var_dump($variable);

// Or error_log for production
error_log(print_r($variable, true));
```

**Go**:

```go
import "fmt"

// Print debugging
fmt.Println("Debug:", variable)

// Or use proper logging
log.Printf("User ID: %s", userID)
```

### Database Queries

Test queries in MySQL console before implementing:

```bash
mysql -u root -p upcycle
```

```sql
-- Test your query
SELECT * FROM users WHERE email = 'test@example.com';

-- Check execution plan
EXPLAIN SELECT * FROM users WHERE email = 'test@example.com';
```

### API Testing

Use cURL or Postman:

```bash
# Test endpoint
curl -X POST http://localhost:9999/users \
  -H "Content-Type: application/json" \
  -d '{"username":"test","email":"test@example.com","password":"test123"}'
```

## Questions?

If you have questions about contributing:

1. Check existing documentation
2. Search closed issues
3. Ask in discussions (if enabled)
4. Contact maintainers

## License

By contributing, you agree that your contributions will be licensed under the same license as the project.

Thank you for contributing to UpcycleConnect!
