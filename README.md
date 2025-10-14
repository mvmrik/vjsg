<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com/)**
- **[Tighten Co.](https://tighten.co)**
- **[WebReinvent](https://webreinvent.com/)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel/)**
- **[Cyber-Duck](https://cyber-duck.co.uk)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Jump24](https://jump24.co.uk)**
- **[Redberry](https://redberry.international/laravel/)**
- **[Active Logic](https://activelogic.com)**
- **[byte5](https://byte5.de)**
- **[OP.GG](https://op.gg)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

---

# Laravel + Vue.js Integration Setup

This Laravel application has been extended with Vue.js frontend integration using Vite and Tailwind CSS.

## Additional Features Added

- ✅ Vue.js 3 with Composition API
- ✅ Vite for fast development and building
- ✅ Tailwind CSS for styling
- ✅ Hot Module Replacement (HMR)
- ✅ Sample Vue components
- ✅ API routes for Laravel-Vue integration

## Available Routes

- **`/`** - Default Laravel welcome page
- **`/app`** - Vue.js application page
- **`/api/hello`** - Sample API endpoint

## Development Commands

### Start Laravel Development Server
```bash
php artisan serve --port=8000
```
Access at: `http://localhost:8000`

### Start Vite Development Server (for HMR)
```bash
npm run dev
```

### Build for Production
```bash
npm run build
```

## Vue.js Project Structure

```
resources/
├── css/
│   └── app.css              # Tailwind CSS imports
├── js/
│   ├── app.js               # Main JS entry point
│   └── components/
│       ├── App.vue          # Main Vue application
│       └── WelcomeCard.vue  # Sample component
└── views/
    ├── welcome.blade.php    # Default Laravel page
    └── app.blade.php        # Vue.js application page
```

## Getting Started with Vue.js

1. **Visit the Vue.js app**: Go to `http://localhost:8000/app`
2. **Development mode**: Run `npm run dev` for hot reloading
3. **Add components**: Create new Vue components in `resources/js/components/`
4. **API integration**: Use Laravel's API routes with Vue.js

Enjoy building with Laravel and Vue.js! 🚀

---

# 🔑 Key Auth System - Система за автентикация с ключове

## Технологична архитектура

### 🔧 Frontend (Vue.js SPA):
- **Vue.js 3** с Composition API
- **Vue Router 4** за SPA навигация
- **Pinia** за state management 
- **Axios** за HTTP заявки
- **Tailwind CSS** за стилизиране

### 🔧 Backend (Laravel API):
- **Laravel 10.x** REST API
- **MySQL** база данни 
- **Custom authentication** със session
- **JSON storage** за ресурси

## Функционалности на системата

### ✨ Основни функции:
- **Vue.js Single Page Application** - плавна навигация без презареждане
- **Уникална система за автентикация** с публичен и частен ключ (без имейл/парола)
- **Респонзив дизайн** - работи на всички устройства
- **Автоматично генериране на ключове** - 64-символни hex ключове
- **Простичък профил** - показва потребителско име и публичен ключ
- **Real-time актуализации** чрез Vue.js reactivity
- **State management** с Pinia store
- **Сигурна сесийна система**

### 🔑 Как да използвате системата:

1. **Отидете на началната страница**: `http://localhost:8000/`
2. **Vue.js SPA Interface**: 
   - Използвайте табовете за превключване между влизане и регистрация
   - Всичко се случва без презареждане на страницата

3. **Регистрирайте се**:
   - Въведете уникално потребителско име
   - Системата автоматично генерира публичен и частен ключ
   - **ВАЖНО**: Копирайте и запазете частния ключ сигурно!

4. **Влезте в профила си**:
   - Въведете вашия 64-символен частен ключ
   - Vue Router автоматично ще ви прехвърли към профилната страница

5. **Профилна страница**:
   - Виждате потребителското си име
   - Публичният ключ е видим и може да се копира
   - Публичният ключ може да се споделя безопасно с други потребители
   - Данните се актуализират мигновено чрез Vue.js reactivity

### 🗄️ Структура на базата данни:

**Таблица `users`:**
```sql
- id (primary key)
- username (unique)
- public_key (64 chars, unique) 
- private_key (64 chars, unique)
- last_active (timestamp)
- created_at, updated_at
```

### � Vue.js Структура:

```
resources/js/
├── app.js                    # Vue.js app entry point
├── stores/
│   └── gameStore.js         # Pinia store за game state
└── components/
    ├── GameApp.vue          # Главен SPA компонент
    ├── HomePage.vue         # Начална страница с auth
    └── ProfilePage.vue      # Профилна страница с игра
```

### ️ API Endpoints:

**SPA Routes (Vue Router):**
- `/` - Начална страница с автентикация (HomePage.vue)
- `/profile` - Профилна страница (ProfilePage.vue)

**Laravel API Endpoints:**
- `POST /register` - Регистрация с автогенериране на ключове
- `POST /login` - Влизане с частен ключ
- `POST /logout` - Изход от системата
- `GET /api/user-data` - Данни за потребителя (защитен)

### 🔧 Техническа информация:

**Backend:**
- Laravel 10.x с персонализирана автентикация
- MySQL база данни (vjsg)
- JSON storage за ресурси
- Custom middleware за проверка на сесии

**Frontend:**  
- Vue.js 3 SPA с Composition API
- Vue Router за client-side routing
- Pinia за централизирано state management
- Axios за API комуникация
- Tailwind CSS за стилизиране
- Responsive дизайн с mobile-first подход
- Smooth анимации и visual feedback

**Сигурност:**
- CSRF защита на всички форми
- Session-based автентикация  
- Unique key генериране с криптографски функции
- Input валидация и sanitization

### 🚀 Deployment инструкции:

1. **Настройка на база данни**:
   ```env
   DB_DATABASE=vjsg
   DB_USERNAME=dbuser  
   DB_PASSWORD=Q2gH6o&PGrhyEyMucHq@4wZJtW5!Mg
   ```

2. **Пуснете миграциите**: `php artisan migrate`

3. **Build assets**: `npm run build`

4. **Стартирайте сървъра**: `php artisan serve --port=8000`

### 🎯 Възможни разширения:

- Avatar система за потребители
- Приятелски списъци (чрез публични ключове)
- Messaging система
- Group management
- File sharing между потребители
- Activity logs
- API за външни приложения

### 🔧 Използване на ключовете:

**Публичен ключ:**
- Може да се споделя свободно
- Използва се за идентификация от други потребители
- Уникален identifier в системата

**Частен ключ:**
- НИКОГА не се споделя
- Необходим за влизане в системата
- Единствения начин за достъп до профила

### 👨‍💻 За разработчици:

**Развитие на системата:**
```bash
# Development mode с hot reload
npm run dev

# Production build
npm run build

# Почистване на кешове
php artisan cache:clear
php artisan config:clear
php artisan route:clear
```

**Добавяне на нови функции:**
- Нови Vue.js компоненти в `resources/js/components/`
- API endpoints в `app/Http/Controllers/`
- Pinia store actions в `resources/js/stores/gameStore.js`
- Нови рутове в `resources/js/app.js` (Vue Router)

**Структура на проекта:**
- **Frontend**: Vue.js 3 + Vue Router + Pinia + Tailwind CSS
- **Backend**: Laravel 10 API + MySQL database
- **Build**: Vite за asset compilation
- **Auth**: Custom key-based authentication система

**Готово! Отворете `http://localhost:8000/` и тествайте системата за автентикация! 🔑**
