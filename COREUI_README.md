# Resource Legends - CoreUI Integration

## Описание
Този проект е Laravel + Vue.js игра за събиране на ресурси, интегрирана с CoreUI за модерен и професионален UI/UX дизайн.

## CoreUI Интеграция

### Инсталирани пакети:
- `@coreui/coreui` - Основни CoreUI стилове и компоненти
- `@coreui/vue` - Vue.js компоненти за CoreUI
- `@coreui/icons-vue` - Vue иконки компоненти
- `@coreui/icons` - CoreUI иконки

### Основни компоненти:

#### 1. GameApp.vue
- Главен layout с CoreUI sidebar, header и footer
- Отзивчива навигация с иконки
- Потребителско меню с avatar
- Login modal интеграция

#### 2. HomePage.vue
- CoreUI дашборд с карточки и статистики
- Responsive grid layout
- Бързи действия и последна активност
- Прогрес бари и badges

#### 3. MapPage.vue
- Leaflet карта интегрирана в CoreUI layout
- Статистики и легенда в странични панели
- CoreUI бутони за управление
- Alert съобщения

#### 4. ProfilePage.vue
- Табове за профил, статистики и настройки
- Форми с CoreUI input компоненти
- Modal за потвърждение на действия
- Avatar и прогрес индикатори

#### 5. InventoryPage.vue
- Grid system за ресурси
- Филтриране по категории
- Dropdown менюта за действия
- Resource details modal

### Ключови функции:

#### Sidebar Navigation
```javascript
const navigation = [
  {
    _name: 'CSidebarNavItem',
    name: 'Начало',
    to: '/',
    icon: 'cilHome'
  },
  // ... други елементи
];
```

#### CoreUI компоненти
- `c-card`, `c-card-header`, `c-card-body`
- `c-button`, `c-dropdown`, `c-modal`
- `c-sidebar`, `c-header`, `c-footer`
- `c-nav`, `c-badge`, `c-progress`
- `c-form-input`, `c-alert`, `c-spinner`

### Стартиране на проекта:

1. **Инсталиране на зависимости:**
```bash
npm install
```

2. **Стартиране на development сървъри:**
```bash
# Vue.js development server
npm run dev

# Laravel server
php artisan serve
```

3. **Build за production:**
```bash
npm run build
```

### URLs:
- Vue.js dev server: `http://localhost:5173`
- Laravel server: `http://127.0.0.1:8000`

### Файлова структура:

```
resources/js/
├── app.js                 # Главен Vue app файл
├── components/
│   ├── GameApp.vue        # Главен layout
│   ├── HomePage.vue       # Начална страница
│   ├── MapPage.vue        # Карта
│   ├── ProfilePage.vue    # Профил
│   ├── InventoryPage.vue  # Инвентар
│   └── LoginForm.vue      # Login форма
└── stores/
    └── gameStore.js       # Pinia store

resources/css/
└── app.css               # CoreUI + TailwindCSS стилове
```

### Теми и стилизиране:

CoreUI предоставя:
- Модерен, плосък дизайн
- Responsive grid система
- Dark/Light теми
- Богата иконна библиотека
- Готови UI компоненти
- Анимации и преходи

### Допълнителни бележки:

1. **TailwindCSS** - запазен за допълнителни стилове
2. **Leaflet** - за интерактивната карта
3. **Pinia** - за state management
4. **Vue Router** - за навигация

Проектът е готов за разработка с пълна CoreUI интеграция!