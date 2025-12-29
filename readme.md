# My Portfolio Tracker

Personal finance and investment portfolio management application with comprehensive
tracking of stocks, cryptocurrencies, dividends, and financial goals.

**Note:** This is my personal project for managing my investment portfolio.
Application runs best in Kubernetes cluster, for full setup see my
[kubedev repository](https://github.com/Mistrfilda/kubedev).

## Technology Stack

### Backend
- **PHP 8.5** with Nette Framework 3.1
- **Doctrine ORM** for database management
- **Symfony Console** for CLI commands
- **RabbitMQ** for asynchronous job processing
- **Monolog** for logging

### Frontend
- **Tailwind CSS 4**
- **TypeScript**
- **AlpineJS**
- **Chart.js**
- **Naja**

### Infrastructure
- **Kubernetes**
- **Docker**

## Features

### Asset Management
- **Stock Positions Tracker**
    - Real-time portfolio valuation
    - Stock asset industry classification
    - Financial metrics and analyst insights

- **Dividends Tracker**
    - Historical dividend records
    - Dividend yield calculations

- **Cryptocurrency Tracker**

- **Currency Exchange Rates**
    - Automatic daily rate updates
    - Multi-currency support for portfolio calculations

- **Portu Portfolios**
    - Portfolio performance statistics
    
### Financial Tracking

- **Expenses Overview**
    - Bank statement parsing
    - Automatic expense categorization

- **Income Overview**
    - Work income tracking (Harvest integration)
    - Bank account transaction monitoring

### Analytics & Visualization
- **Comprehensive Statistics**
    - Profit/loss calculations
    - Currently invested amount tracking
    - Real-time portfolio value
    - Historical performance graphs
    - Asset allocation charts

- **Goal Tracking**
    - Financial goal setting and monitoring
    - Automatic progress updates

### System Features
- **Monitoring & Notifications**
    - System health monitoring
    - Alert notifications
    - Push monitors for critical events

- **Background Job Processing**
    - RabbitMQ-based task queues
    - Scheduled data downloads
    - Async processing of heavy operations

## 🛠️ Development

### Requirements
- PHP 8.5+
- Composer
- Node.js & Yarn
- Docker & Docker Compose
- Kubernetes cluster (for production)
