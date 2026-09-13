# Componenta Skeleton

Стартовый проект для PHP 8.4+ с пресетами HTTP, CLI и WebSocket.

## Установка

```bash
composer create-project componenta/skeleton my-app
cd my-app
php bin/console.php list
```

Установщик выбирает зависимости, создаёт точки входа и локальный `.env`, затем удаляет свои установочные файлы. Без интерактивного ввода выбирается Web с Nyholm PSR-7, Plates, Pest, CQRS и политиками.

| Пресет | Возможности |
|---|---|
| Web | HTTP, маршрутизация и опциональные шаблоны |
| API | HTTP и маршрутизация, по умолчанию без шаблонов |
| Full | HTTP, шаблоны, CQRS, политики, аутентификация и Cycle ORM |
| CLI | Консольные команды |
| WebSocket | WebSocket-приложение |

HTTP-пресеты поддерживают Nyholm, Diactoros, Guzzle и Slim. Интерактивная установка позволяет выбрать Pest или PHPUnit и дополнительные интеграции. Full использует Nyholm, Plates и Pest. WebSocket можно добавить к другому пресету.

## Конфигурация и жизненный цикл

Точки входа загружают Composer и вызывают `Componenta\App\run()` с областью выполнения и `PathResolver`. Приложение загружает `config/container.php`.

`config/config.php` возвращает `ConfigDefinition` с провайдерами, конфигурационными файлами и опциональными директориями дискаверинга. `Componenta\App\Config\ConfigFactory` подготавливает композицию из runtime Config и отдельных определений зависимостей. `Componenta\DI\ContainerFactory` получает эти объекты и создаёт контейнер. Фабрики получают `ContainerValue` и обращаются к тому же Config через `$container->config`.

Composer генерирует `config/componenta-providers.php` из метаданных пакетов. Провайдеры приложения можно помечать `#[AsConfig]` и размещать в `src/`; конфигурационные файлы находятся в `config/autoload/`.

Конфигурация собирается в каждом окружении. Настроенный источник классов передаётся через DI. Bootloader'ы подготавливают выбранную область выполнения.

## Сборка и развёртывание

```bash
php bin/console.php app:build
```

Обычная консольная команда использует существующий контейнер. Билдеры создаются только при выполнении; просмотр списка команд и справки сохраняет ленивость сборочных зависимостей.

Пакеты и приложение регистрируют упорядоченный список сервисов в `Componenta\App\ConfigKey::BUILDERS`. Билдер реализует `ApplicationBuilderInterface::build(): void` и получает зависимости через конструктор. Каждый билдер атомарно записывает собственный артефакт. Ошибка останавливает последовательность; уже записанные результаты предыдущих билдеров сохраняются.

Интеграции могут генерировать карты CQRS, маршрутов, перехватчиков и валидации. Runtime-фабрики выбирают оптимизированную реализацию и поддерживаемый fallback к исходным данным. Пользовательские провайдеры и нативное поведение атрибутов сохраняются. Валидация использует атрибутный провайдер для классов, правила которых нельзя экспортировать. Policy использует обычный провайдер и кеш в памяти.

Для развёртывания задайте `APP_ENV=production`. Запуск не требует артефактов сборки, runtime не вызывает билдеры. Выполняйте сборку при развёртывании для использования оптимизаций и перезапускайте долгоживущие worker'ы после обновления кода и артефактов.

## Разработка

- `composer test` проверяет настоящий bootstrap и справку команды сборки.
- `composer serve` запускает HTTP-сервер для разработки.
- `bin/console.php` — точка входа консоли.
- HTTP-пресеты добавляют `public/index.php`, конфигурацию маршрутов и middleware.
- WebSocket-пресеты добавляют `bin/websocket.php` и `config/websocket.php`.

Подробности находятся в документации [App](https://github.com/Componenta/app), [DI](https://github.com/Componenta/di), [Config](https://github.com/Componenta/config), [Router App](https://github.com/Componenta/router-app), [CQRS App](https://github.com/Componenta/cqrs-app), [Interceptor](https://github.com/Componenta/interceptor), [Validation App](https://github.com/Componenta/validation-app), [Policy](https://github.com/Componenta/policy), [Cycle App](https://github.com/Componenta/cycle-app) и [WebSocket App](https://github.com/Componenta/websocket-app).
