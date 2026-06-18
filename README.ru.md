# Componenta Skeleton

Componenta Skeleton - стартовая сборка Componenta Framework для PHP 8.4+ приложений. Она дает готовый проект с точками входа, конфигурацией, контейнером, обработкой ошибок, обнаружением классов и пресетами HTTP, API, CLI и WebSocket.

Скелетон показывает, как фреймворк собирает приложение из пакетов Componenta: Composer находит провайдеры пакетов, конфигурация объединяет их с файлами проекта, контейнер строит сервисы, а `Runner` запускает нужную область выполнения: HTTP, CLI или WebSocket.

## Установка

```bash
composer create-project componenta/skeleton my-app
```

Во время `composer create-project` `Installer::install()` запускается до разрешения зависимостей и подготавливает набор пакетов выбранного пресета. В интерактивном режиме установщик задает вопросы, в неинтерактивном использует значения по умолчанию. После выбора он добавляет нужные Composer-зависимости, создает точки входа и удаляет установочные файлы.

Подробнее: [`componenta/composer-plugin`](https://github.com/componenta/composer-plugin/blob/main/README.ru.md) описывает обнаружение провайдеров через Composer, [`componenta/app`](https://github.com/componenta/app/blob/main/README.ru.md) описывает запуск приложения.

## Пресеты

| Пресет | Что создается |
|---|---|
| Web | HTTP-приложение с `public/index.php`, маршрутизацией, `config/routes.php`, `config/pipeline.php`, `composer serve` и шаблонами, если выбран шаблонизатор. |
| Full | Web-приложение с пакетами выбора по умолчанию, CQRS, политиками доступа, аутентификацией, Cycle ORM и опциональным WebSocket-сервером. |
| API | HTTP-приложение с маршрутизацией и JSON-ответом приветствия, по умолчанию без шаблонов. |
| CLI | Консольное приложение без HTTP, WebSocket, маршрутизации и публичной точки входа. |
| WebSocket | WebSocket-приложение с `bin/websocket.php` и WebSocket-конфигурацией, без HTTP-точки входа. |

В интерактивном режиме пункты выбираются по номеру: `0` для первого варианта, `1` для второго и так далее. HTTP-пресеты предлагают выбрать реализацию PSR-7: Nyholm, Diactoros, Guzzle или Slim. HTTP-пресеты также предлагают выбрать шаблонизатор: Plates по умолчанию для Web, отсутствие шаблонизатора по умолчанию для API. Тестовый фреймворк тоже выбирается во время установки: Pest по умолчанию или PHPUnit. Пресет Full использует варианты выбора по умолчанию: Nyholm PSR-7, Plates и Pest; отдельно он спрашивает, нужен ли WebSocket-сервер.

В неинтерактивном режиме используется Web-пресет с Nyholm PSR-7, шаблонами, Pest, CQRS и политиками доступа. Аутентификация, Cycle ORM и WebSocket-дополнение по умолчанию не включаются.

Подробнее: [`componenta/http-psr`](https://github.com/componenta/http-psr/blob/main/README.ru.md) описывает HTTP-фабрики, [`componenta/http-psr-nyholm`](https://github.com/componenta/http-psr-nyholm/blob/main/README.ru.md) показывает одну из PSR-7 интеграций, [`componenta/templater-app`](https://github.com/componenta/templater-app/blob/main/README.ru.md) описывает подключение шаблонов.

## Опции установщика

После выбора пресета установщик настраивает зависимости и файлы проекта:

- HTTP-пресеты создают `public/index.php`, `config/routes.php`, `config/pipeline.php`, `src/Welcome.php` и безопасный шаблон ошибки `templates/error/500.phtml`;
- HTTP-пресеты при выбранных шаблонах дополнительно создают `templates/welcome.phtml` и подключают `componenta/templater-app`;
- CLI-пресет не создает HTTP и WebSocket-инфраструктуру;
- WebSocket-пресет создает `bin/websocket.php`, `config/websocket.php` и стартовое приложение `src/WebSocket/WelcomeApplication.php`;
- CQRS, политики доступа, аутентификацию, Cycle ORM и WebSocket-дополнение можно включить или выключить интерактивно; пресет Full включает CQRS, политики доступа, аутентификацию и Cycle ORM автоматически;
- если включена аутентификация, CQRS и политики доступа включаются принудительно;
- CLI-команды запускаются через `php bin/console.php`.

Подробнее: [`componenta/cqrs-app`](https://github.com/componenta/cqrs-app/blob/main/README.ru.md) описывает интеграцию команд и запросов, [`componenta/policy-app`](https://github.com/componenta/policy-app/blob/main/README.ru.md) описывает интеграцию политик, [`componenta/auth`](https://github.com/componenta/auth/blob/main/README.ru.md) описывает аутентификацию, [`componenta/cycle-app`](https://github.com/componenta/cycle-app/blob/main/README.ru.md) описывает Cycle ORM.

## Жизненный цикл приложения

1. Внешняя точка входа (`public/index.php`, `bin/console.php` или `bin/websocket.php`) подключает автозагрузку Composer, создает `PathResolver` и вызывает `Componenta\App\run()`.
2. `Componenta\App\run()` получает `Scope`: `Scope::HTTP`, `Scope::CLI` или `Scope::WEBSOCKET`.
3. `config/container.php` вызывает `ConfigFactory::create()` и `ContainerFactory::create()`.
4. `config/config.php` возвращает `ConfigDefinition`: список провайдеров конфигурации и директории для обнаружения классов.
5. Контейнер строится из конфигурации, найденных классов и сервисов, добавленных пакетами.
6. `Componenta\App\run()` получает `Componenta\Config\Config` из контейнера и оборачивает контейнер вместе с конфигом в `Componenta\Config\ContainerValue`.
7. `Runner::run()` получает текущий `Scope` и этот `ContainerValue`, выбирает адаптер для области выполнения, создает целевой объект запуска и запускает приложение.
8. Загрузчики получают `BootContext`, в котором находятся `ContainerValue`, текущая область выполнения и целевой объект конкретной области. Они выполняют подготовку окружения: регистрируют команды, маршруты, обработчики, слушателей, шаблоны или WebSocket-приложения.

Этот порядок одинаковый для всех пресетов. Отличается только область выполнения и набор установленных интеграционных пакетов.

Обнаружение классов является частью этого жизненного цикла. `ClassDiscoveryBootloader` восстанавливает подготовленные данные обнаружения в окружениях вне разработки и запускает обнаружение в режиме разработки только там, где приложению разрешено сканировать исходный код.

Подробнее: [`componenta/app`](https://github.com/componenta/app/blob/main/README.ru.md) описывает `Scope`, `Runner`, адаптеры и загрузчики; [`componenta/app-http`](https://github.com/componenta/app-http/blob/main/README.ru.md) описывает HTTP-область; [`componenta/app-console`](https://github.com/componenta/app-console/blob/main/README.ru.md) описывает CLI-область; [`componenta/websocket-app`](https://github.com/componenta/websocket-app/blob/main/README.ru.md) описывает WebSocket-область.

## Конфигурация

Главная конфигурация находится в `config/config.php`. Она подключает провайдеры пакетов, атрибутные провайдеры, конфигурацию консольных команд и автоподключаемую проектную конфигурацию:

```php
return new ConfigDefinition(
    providers: [
        new ComposerPackageConfigProvider($paths->resolve('config/componenta-providers.php')),
        new AttributeConfigProvider(),
        new FileProvider($paths->resolve('config/console.php')),
        new FileProvider($paths->resolve('config/autoload/{{,*.}global,{,*.}local}.{php,yaml,json}')),
    ],
    discovery: new DiscoveryDefinition(
        directories: ['src'],
    ),
);
```

`ComposerPackageConfigProvider` подключает провайдеры установленных пакетов. `AttributeConfigProvider` подключает конфигурацию из атрибутов, найденных в коде проекта. `config/console.php` регистрирует проектные консольные команды в общем графе конфигурации. Последний `FileProvider` подключает проектные файлы из `config/autoload`.

`ConfigDefinition` также задает область обнаружения классов. В `DiscoveryDefinition::directories` указываются директории, которые можно сканировать; пути могут быть относительными к базовой директории приложения или абсолютными. В `DiscoveryDefinition::exclude` указываются шаблоны директорий или файлов, которые нужно исключить из сканирования, например сгенерированный код, временные классы или интеграции, которые не должны участвовать в атрибутном обнаружении.

```php
discovery: new DiscoveryDefinition(
    directories: ['src'],
    exclude: ['src/Generated', 'src/Legacy'],
),
```

### Как собирается итоговая конфигурация

`ConfigFactory::create()` сначала загружает `.env` из корня приложения с переопределением текущих значений окружения. Если `.env` отсутствует, используются значения из окружения процесса. После этого поведение зависит от `APP_ENV`.

В `APP_ENV=development` фабрика:

1. загружает `config/config.php` и получает `ConfigDefinition`;
2. выполняет первый проход по провайдерам, чтобы получить базовую конфигурацию и вычислить `CacheLayout`;
3. запускает обнаружение классов из `DiscoveryDefinition`, если оно задано;
4. передает найденные классы провайдерам, которые реализуют `DiscoveryAwareConfigProviderInterface`;
5. оборачивает `AttributeConfigProvider` в кеширующий провайдер, если доступен файл кеша атрибутной конфигурации;
6. применяет compile-delta cache, если он был подготовлен предыдущей сборкой;
7. сливает провайдеры в итоговый `Componenta\Config\Config`.

Порядок провайдеров важен: более поздние провайдеры могут дополнять или переопределять значения, если соответствующий пакетный merge-механизм это поддерживает. В базовом скелетоне сначала идут провайдеры установленных пакетов, затем проектные `#[AsConfig]`-провайдеры, затем `config/console.php`, затем файлы `config/autoload`.

Если `APP_ENV` отличается от `development`, `ConfigFactory` не читает `config/config.php`, не создает провайдеры и не сканирует `src`. Он загружает готовый `var/cache/build/config.cache.php`, созданный командой `app:build`. Поэтому production-старт детерминирован: он зависит от подготовленного сборочного кеша, а не от runtime discovery.

Итоговый объект `Config` затем передается в `ContainerFactory`, регистрируется в контейнере под `Componenta\Config\Config::class` и алиасом `'config'`, а также доступен фабрикам как `$container->config`, если фабрика типизирует аргумент как `Componenta\Config\ContainerValue`. Этот же `ContainerValue` получают загрузчики приложения через `BootContext::$container`.

Файлы `*.global.*` предназначены для общей конфигурации. Файлы `*.local.*` предназначены для локальных настроек окружения и обычно не коммитятся. Установщик создает `config/autoload/app.local.php` из локального шаблона пакета и удаляет `app.local.php.dist` из установленного проекта.

Подробнее: [`componenta/config`](https://github.com/componenta/config/blob/main/README.ru.md) описывает провайдеры конфигурации и загрузку файлов, [`componenta/app`](https://github.com/componenta/app/blob/main/README.ru.md) описывает `ConfigFactory`, окружение и структуру кеша.

## Провайдеры конфигурации и `#[AsConfig]`

Провайдер конфигурации - это вызываемый класс, который возвращает массив конфигурации. Через такие провайдеры пакеты регистрируют фабрики, алиасы, автосвязывание, загрузчики приложения, промежуточные обработчики, участников сборочного кеша и собственные ключи конфигурации.

Пакетные провайдеры подключаются через `componenta/composer-plugin`: каждый пакет объявляет класс провайдера в `extra.componenta.config-providers`, плагин собирает их в `config/componenta-providers.php`, а `ComposerPackageConfigProvider` загружает этот файл.

Проектный провайдер можно положить в `src/` и пометить `#[AsConfig]`. Минимальный прикладной провайдер выглядит так:

```php
namespace App;

use App\Boot\WarmupBootloader;
use Componenta\App\Config\AsConfig;
use Componenta\App\ConfigKey;

#[AsConfig]
final class ConfigProvider extends \Componenta\Config\ConfigProvider
{
    protected function getConfig(): array
    {
        return [
            ConfigKey::BOOTLOADERS => [
                WarmupBootloader::class,
            ],
        ];
    }
}
```

`AttributeConfigProvider` находит классы с `#[AsConfig]` в директориях обнаружения, создает объект без аргументов, вызывает его как функцию и сливает возвращенный массив в общую конфигурацию. Провайдер должен возвращать массив или объект, который можно перебрать. Если нужна конфигурация, зависящая от окружения конкретной машины, используйте `config/autoload/*.local.php`; если нужна конфигурация пакета или модуля приложения, используйте `#[AsConfig]`.

Атрибут `#[AsConfig]` может стоять на классе, функции или методе, но текущий `AttributeConfigProvider` сканирует найденные классы и вызывает провайдеры, размещенные на классах. Для скелетона основной поддерживаемый сценарий - класс с `__invoke()` в `src/`.

Во время `composer create-project` `Installer::install()` запускается на событии `post-root-package-install`, до разрешения зависимостей выбранного приложения. Установщик переписывает `composer.json` под выбранный пресет и синхронизирует активный root package Composer, поэтому в разрешении зависимостей участвуют только выбранная PSR-7 реализация и выбранные опциональные пакеты. `src/ConfigProvider.php` генерируется под выбранный пресет. HTTP-пресеты регистрируют `InterceptorConfigKey::HTTP_INTERCEPTORS` с `AttributeInterceptor::class`. CQRS-пресеты регистрируют `CqrsConfigKey::COMMAND_MIDDLEWARES` и `CqrsConfigKey::QUERY_MIDDLEWARES`; если выбраны политики доступа, в эти цепочки добавляются промежуточные обработчики политик. CLI-пресет и WebSocket-пресет оставляют этот провайдер минимальным и не создают HTTP или CQRS-конфигурацию.

Подробнее: [`componenta/config`](https://github.com/componenta/config/blob/main/README.ru.md) описывает базовый `ConfigProvider`, [`componenta/app`](https://github.com/componenta/app/blob/main/README.ru.md) описывает `AttributeConfigProvider` и обнаружение классов.

## Обнаружение пакетов

Пакеты Componenta объявляют свои провайдеры в `composer.json` через `extra.componenta.config-providers`. `componenta/composer-plugin` читает эти метаданные после `composer install`, `composer update` и `composer dump-autoload`, затем записывает `config/componenta-providers.php`.

Этот файл возвращает массив классов провайдеров и не редактируется вручную. Установщик не записывает его напрямую: файл появляется или обновляется, когда отрабатывает Composer-плагин. До первого запуска плагина файл может отсутствовать; `ComposerPackageConfigProvider` в этом случае возвращает пустую конфигурацию. Если пакет удален из Composer, его провайдер исчезнет из сгенерированного файла при следующем событии Composer.

Подробнее: [`componenta/composer-plugin`](https://github.com/componenta/composer-plugin/blob/main/README.ru.md) описывает формат метаданных, события Composer и атомарную запись файла провайдеров.

## Обнаружение классов

`DiscoveryDefinition` задает директории, которые фреймворк сканирует в режиме разработки. В скелетоне это `src`. Найденные классы передаются пакетам, которые умеют читать атрибуты:

- `componenta/app-console` в режиме разработки находит консольные команды с `#[AsCommand]`;
- `componenta/router-app` находит HTTP-маршруты;
- `componenta/cqrs-app` находит обработчики команд и запросов;
- `componenta/policy-app` готовит карту политик;
- `componenta/interceptor-app` готовит карту перехватчиков.

Обнаружение реализовано через слушателей классов. Пакет регистрирует слушателя в своем провайдере конфигурации, приложение создает `ClassListenerProvider`, а `ClassDiscoveryBootloader` управляет жизненным циклом:

1. Если для текущего окружения есть сборочный кеш, загрузчик восстанавливает скомпилированное состояние слушателей.
2. В режиме разработки, если восстановление невозможно, загрузчик сканирует директории из `DiscoveryDefinition` через `ClassIteratorInterface`.
3. `ClassListenerNotifier` передает каждый найденный `ClassInfo` зарегистрированным слушателям.
4. После обработки всех классов каждый `FinalizableListenerInterface` финализируется ровно один раз.
5. Финализированное состояние затем используют локаторы времени выполнения, роутер, CQRS-карты, карты политик и карты перехватчиков.

Финализируемые слушатели отделяют сбор метаданных от использования во время выполнения. `handle()` собирает сырые сведения о классе, а `finalize()` строит стабильную структуру данных для приложения. Слушатель, который поддерживает компиляцию, предоставляет `FinalizationStateInterface`; компиляторы сборки проверяют, что такой слушатель уже финализирован перед сериализацией. Повторный вызов `finalize()` может бросить `FinalizationExceptionInterface`; одноразовые слушатели бросают `ListenerAlreadyFinalizedException`.

Компиляторы регистрируются интеграционными пакетами как участники сборки. Они не сканируют классы сами. Компилятор получает уже финализированное состояние слушателя или локатора, проверяет, что его безопасно компилировать, и записывает PHP-артефакт, который можно подключить при старте боевого окружения. Соответствующий восстановитель читает этот артефакт и возвращает подготовленное состояние в сервис времени выполнения. Ответственность разделена явно: слушатели обнаруживают, финализация завершает данные, компиляторы сохраняют их, восстановители загружают.

Старт боевого окружения должен быть детерминированным: приложение не должно сканировать исходники проекта, чтобы найти маршруты, команды, обработчики, политики или перехватчики. Оно должно восстанавливать данные, подготовленные командой `app:build`, из `var/cache/build`.

Подробнее: [`componenta/class-finder`](https://github.com/componenta/class-finder/blob/main/README.ru.md) описывает поиск классов, [`componenta/router-app`](https://github.com/componenta/router-app/blob/main/README.ru.md), [`componenta/cqrs-app`](https://github.com/componenta/cqrs-app/blob/main/README.ru.md), [`componenta/policy-app`](https://github.com/componenta/policy-app/blob/main/README.ru.md) и [`componenta/interceptor-app`](https://github.com/componenta/interceptor-app/blob/main/README.ru.md) описывают свои карты обнаружения.

## Режим разработки и сборочный режим

По умолчанию `.env.dist` содержит:

```dotenv
APP_ENV=development
APP_DEBUG=true
```

В режиме `APP_ENV=development` приложение каждый запуск собирает конфигурацию из провайдеров, файлов и атрибутов, сканирует указанные директории и использует dev-кеши для ускорения повторных запусков.

Если `APP_ENV` отличается от `development`, `ConfigFactory` считает, что приложение работает из сборочного кеша, и читает `var/cache/build/config.cache.php`. В этом режиме проектное определение конфигурации не пересобирается на каждом запросе. Сборочный кеш должен быть подготовлен до запуска приложения в таком окружении; если файла нет, запуск должен завершиться ошибкой конфигурации.

Для `APP_ENV=production` контейнер пытается использовать подготовленный файл `var/cache/build/container.cache.php`. Если отдельный оптимизирующий шаг сборки создал `var/cache/build/container.factory.php` и выбранный режим кеша контейнера позволяет его использовать, `ContainerFactory` может взять и этот factory-файл. Стандартная команда `app:build` записывает `config.cache.php` и `container.cache.php`; `container.factory.php` она не генерирует.

`app:build` - команда подготовки боевого окружения. Она должна запускаться с `APP_ENV=development`, потому что команда собирает кеш из исходной конфигурации и метаданных обнаружения классов режима разработки. Ее нужно запускать до переключения приложения в окружение вне разработки:

```bash
APP_ENV=development php bin/console.php app:cache:clear --build
APP_ENV=development php bin/console.php app:build
APP_ENV=production php bin/console.php list
```

Сборка намеренно выполняется при доступном обнаружении классов режима разработки. Команда отказывается запускаться из production-cache. Она собирает провайдеры пакетов, проектные провайдеры, провайдеры `#[AsConfig]`, конфигурацию команд, слушателей обнаружения классов, скомпилированные вызовы `#[Boot]`, карты маршрутов, CQRS-карты, карты политик, карты перехватчиков, кеш конфигурации и кеш контейнера. После этого боевое окружение читает подготовленные файлы и не повторяет обнаружение классов.

Основные артефакты сборки лежат в `var/cache/build/`:

| Файл | Кто создает | Назначение |
|---|---|---|
| `config.cache.php` | `app:build` | Экспортированный итоговый `Config`, включая результат компиляторов discovery. |
| `container.cache.php` | `app:build` | Нормализованный граф DI-зависимостей для `ContainerFactory`. |
| `routes.cache.php` | Компилятор `componenta/router-app`, если установлена маршрутизация | Скомпилированная таблица маршрутов, восстанавливаемая без сканирования атрибутов. |
| `policies.cache.php` | Компилятор `componenta/policy-app`, если установлены политики доступа | Скомпилированная карта политик. |
| `interceptors.cache.php` | Компилятор `componenta/interceptor-app`, если установлены перехватчики | Скомпилированная карта атрибутов перехватчиков. |
| `discovery.cache.php` / `di-plans.cache.php` | Участники сборки discovery и DI, если они настроены | Дополнительные артефакты компиляции для интеграций фреймворка. |
| `preload.php` | `app:preload` | Опциональный PHP preload-файл, построенный из существующих артефактов сборки. |

Если окружение вне разработки запускается без обязательного сборочного кеша, старт должен завершиться понятной ошибкой конфигурации, а не молча сканировать исходники.

`app:preload` можно запускать после `app:build`, если деплой использует PHP preload. Сгенерированный preload-файл строится на основе артефактов сборочного кеша.

`APP_DEBUG` отвечает за отображение подробностей ошибки пользователю в штатной HTTP-обработке ошибок. Сгенерированная HTTP-точка входа также ловит ошибки, которые происходят до запуска контейнера, пишет их через `error_log()`, возвращает статус `500` и рендерит `templates/error/500.phtml`. Эта безопасная страница этапа загрузки используется независимо от `APP_DEBUG`, потому что штатный рендерер ошибок на этой стадии может быть ещё недоступен.

Подробнее: [`componenta/app`](https://github.com/componenta/app/blob/main/README.ru.md) описывает `ConfigFactory`, `CacheLayout` и поддержку сборочного кеша, [`componenta/error-handler-app`](https://github.com/componenta/error-handler-app/blob/main/README.ru.md) описывает HTTP-обработку ошибок и безопасный рендеринг.

## Контейнер

`config/container.php` является точкой сборки контейнера. Он загружает конфигурацию и возвращает PSR-11 контейнер:

```php
$result = ConfigFactory::create(
    paths: $paths,
    definition: static fn () => require $paths->resolve('config/config.php'),
);

return ContainerFactory::create($paths, $result->config, $result->discovered);
```

`ContainerFactory` добавляет в контейнер `PathResolverInterface`, найденные классы и сервисы, объявленные провайдерами. Он также регистрирует итоговый `Config` и делает его доступным через `ContainerValue` — типизированную обертку, которую используют фабрики и загрузчики фреймворка. Проект может расширять контейнер через файлы `config/autoload/*.php` или через `App\ConfigProvider` с атрибутом `#[AsConfig]`.

Фабрики могут типизировать первый аргумент как `Psr\Container\ContainerInterface` или как `Componenta\Config\ContainerValue`. Для новых прикладных фабрик предпочтительнее `ContainerValue`, если нужен доступ к конфигу или optional lookup:

```php
use Componenta\Config\ConfigPath;
use Componenta\Config\ContainerValue;
use Psr\Log\LoggerInterface;

static function (ContainerValue $container): App\Service\Reporter {
    return new App\Service\Reporter(
        logger: $container->get(LoggerInterface::class, LoggerInterface::class),
        enabled: $container->config->bool(new ConfigPath('reporting.enabled'), true),
    );
}
```

Подробнее: [`componenta/di`](https://github.com/componenta/di/blob/main/README.ru.md) описывает DI-контейнер, фабрики, атрибуты и резолверы свойств; [`componenta/config`](https://github.com/componenta/config/blob/main/README.ru.md) описывает формат конфигурационных массивов.

## Конфиг приложения

Итоговая конфигурация приложения представлена объектом `Componenta\Config\Config`. Его создает `ConfigFactory::create()`, а `ContainerFactory` кладет этот же объект в контейнер под `Config::class` и алиасом `'config'`.

```php
use Componenta\Config\Config;
use Componenta\Config\ConfigPath;

/** @var \Psr\Container\ContainerInterface $container */
$config = $container->get(Config::class);

$name = $config->string(new ConfigPath('app.name'), 'Componenta App');
$debug = $config->bool(new ConfigPath('app.debug'), false);
```

Внутри сервисов можно получать весь конфиг через конструктор:

```php
namespace App\Service;

use Componenta\Config\Config;
use Componenta\Config\ConfigPath;

final readonly class FeatureFlags
{
    public function __construct(
        private Config $config,
    ) {}

    public function enabled(string $name): bool
    {
        return $this->config->bool(new ConfigPath("features.$name"), false);
    }
}
```

Если сервису нужно одно значение, используйте DI-атрибут `#[Config]`. Строковый ключ читается буквально, а `ConfigPath` включает доступ к вложенному массиву через точки:

```php
namespace App\Service;

use Componenta\Config\ConfigPath;
use Componenta\DI\Attribute\Config;

final readonly class MailerOptions
{
    public function __construct(
        #[Config(new ConfigPath('mail.from'))]
        public string $from,

        #[Config(new ConfigPath('mail.retries'), default: 3)]
        public int $retries,
    ) {}
}
```

Основные методы `Config`:

| Метод | Назначение |
|---|---|
| `get(string\|ConfigPath $key, mixed $default = DefaultValue::None)` | Возвращает значение как есть. Без значения по умолчанию бросает исключение, если ключ не найден. |
| `has(string\|ConfigPath $key)` | Проверяет наличие ключа. |
| `string()`, `int()`, `float()`, `bool()`, `array()` | Возвращают значение с приведением типа. |
| `only(string\|ConfigPath\|array $keys)` | Возвращает новый `Config` только с выбранными ключами. |
| `except(string\|ConfigPath\|array $keys)` | Возвращает новый `Config` без выбранных ключей. |
| `toArray()` | Возвращает весь массив конфигурации. |

Строка в `get('database.host')` ищет буквальный ключ `$config['database.host']`. Для вложенного доступа нужен `new ConfigPath('database.host')`, который читает `$config['database']['host']`.

У `Config` есть свойство `environment`. Через него можно читать переменные окружения, которые были загружены из `.env` или глобального окружения:

```php
$env = $config->environment;

$isProduction = $env?->match('APP_ENV', 'production') ?? false;
$timezone = $env?->string('APP_TIMEZONE', 'UTC') ?? 'UTC';
```

В конфигурационных файлах и провайдерах обычно возвращают массив, а не читают `Config`. Чтение готового `Config` нужно в сервисах, фабриках и загрузчиках, когда приложение уже собрало все провайдеры.

Подробнее: [`componenta/config`](https://github.com/componenta/config/blob/main/README.ru.md) описывает `Config`, `ConfigPath`, `Environment`, загрузку файлов и правила слияния; [`componenta/di`](https://github.com/componenta/di/blob/main/README.ru.md) описывает атрибут `#[Config]`.

## Загрузчики приложения

Загрузчик приложения выполняет стартовую настройку перед запуском текущей области: HTTP, CLI или WebSocket. Он нужен для действий, которые требуют уже собранный контейнер и готовый объект приложения: подключить HTTP-конвейер, зарегистрировать консольные команды, восстановить карты обнаружения, назначить WebSocket-приложение, выполнить прикладной warmup.

`Runner` создает `BootContext`, `BootloaderProvider` читает список классов из `ConfigKey::BOOTLOADERS`, фильтрует их по `Scope`, затем получает подходящие загрузчики из контейнера и вызывает `boot()`:

```php
use Componenta\App\ConfigKey;

return [
    ConfigKey::BOOTLOADERS => [
        App\Boot\WarmupBootloader::class,
    ],
];
```

В скелетоне эта регистрация находится в `src/ConfigProvider.php`, который помечен `#[AsConfig]`. Если добавляете собственный загрузчик, добавьте его в `getConfig()` и зарегистрируйте класс как autowired service:

```php
namespace App;

use App\Boot\WarmupBootloader;
use App\Service\WarmupService;
use Componenta\App\Config\AsConfig;
use Componenta\App\ConfigKey;

#[AsConfig]
final class ConfigProvider extends \Componenta\Config\ConfigProvider
{
    protected function getConfig(): array
    {
        return [
            ConfigKey::BOOTLOADERS => [
                WarmupBootloader::class,
            ],
        ];
    }

    protected function getAutowires(): array
    {
        return [
            WarmupBootloader::class,
            WarmupService::class,
        ];
    }
}
```

Прикладной загрузчик можно создать через базовый класс `Bootloader`. В этом варианте `__invoke()` вызывается через DI, поэтому зависимости можно получать параметрами метода:

```php
namespace App\Boot;

use App\Service\WarmupService;
use Componenta\App\Boot\BootContext;
use Componenta\App\Boot\Bootloader;
use Componenta\App\Scope;
use Componenta\Config\ConfigPath;
use Componenta\Scope\Scopes;

final class WarmupBootloader extends Bootloader
{
    public Scopes $scopes {
        get => Scopes::of(Scope::HTTP);
    }

    public function supports(BootContext $context): bool
    {
        return $context->container->config->bool(new ConfigPath('warmup.enabled'), false);
    }

    public function __invoke(WarmupService $warmup): void
    {
        $warmup->run();
    }
}
```

Если нужен полный контроль, реализуйте `BootloaderInterface` напрямую. Тогда внутри `boot()` доступны `BootContext::$container`, `BootContext::$scope` и `BootContext::target()`. `BootContext::$container` — это `Componenta\Config\ContainerValue`, а не сырой PSR-11 контейнер: он дает lookup сервисов, типизированные helper-методы, optional `find()` fallback и доступ к собранной конфигурации приложения через `$context->container->config`:

```php
namespace App\Boot;

use Componenta\App\Boot\BootContext;
use Componenta\App\Boot\BootloaderInterface;
use Componenta\App\Boot\Target\HttpBootTargetInterface;
use Componenta\App\Scope;

final class ExtraHttpPipelineBootloader implements BootloaderInterface
{
    public function boot(BootContext $context): void
    {
        $http = $context->target(HttpBootTargetInterface::class);
        $http->pipe(\App\Http\Middleware\RequestIdMiddleware::class);
    }

    public function supports(BootContext $context): bool
    {
        return $context->scope === Scope::HTTP;
    }
}
```

Для обычных глобальных промежуточных обработчиков HTTP предпочтительнее `config/pipeline.php`. Собственный HTTP-загрузчик нужен, когда регистрация зависит от контейнера, конфигурации или пакета-интеграции. Для CLI используйте `ConsoleBootTargetInterface`, для WebSocket - `WebSocketBootTargetInterface`.

Фреймворковые пакеты также используют загрузчики для подготовки сервисов времени выполнения, которые зависят от метаданных. `ClassDiscoveryBootloader` восстанавливает или строит состояние слушателей обнаружения классов до того, как оно понадобится маршрутизации, обнаружению команд, CQRS, политикам доступа или перехватчикам. Код приложения не должен сканировать классы из контроллеров, обработчиков команд или промежуточных обработчиков; атрибутные метаданные фреймворка должны готовиться слушателями и сборочным кешем.

### Boot-методы

Для небольших стартовых хуков найденный класс может объявить публичные методы с атрибутом `#[Boot]`. Такой метод вызывается до запуска выбранной области приложения. Используйте это для легкого прогрева или регистрации, когда логика принадлежит прикладному классу и ей нужны значения из контейнера или конфигурации.

```php
namespace App;

use Componenta\App\Boot\Boot;
use Componenta\DI\Attribute\Config;
use Componenta\DI\Attribute\Env;
use Componenta\DI\Attribute\EntryId;

final class Welcome
{
    #[Boot(
        priority: 20,
        params: [
            'service' => new EntryId(AppWarmup::class),
            'name' => new Config('app.name', default: 'Componenta'),
            'debug' => new Env('APP_DEBUG', default: false),
        ],
    )]
    public static function boot(AppWarmup $service, string $name, bool $debug): void
    {
        $service->prepare($name, $debug);
    }
}
```

Boot-методы выполняются по убыванию `priority`. В массиве `params` можно передавать обычные значения и метаданные DI:

- `EntryId` получает сервис из контейнера;
- `Config` читает значение из собранной конфигурации приложения;
- `Env` читает значение из окружения, привязанного к конфигурации.

В режиме разработки `BootMethodInvocation` работает как слушатель обнаружения классов: он читает атрибуты `#[Boot]` во время сканирования директорий загрузчиком `ClassDiscoveryBootloader`, а затем вызывает финализированный список. Во время `app:build` `BootInvocationCompiler` записывает финализированный список в сборочный кеш конфигурации. В боевом окружении `CompiledBootInvocationBootloader` запускается только при `APP_ENV=production` и выполняет скомпилированный список из `ConfigKey::BOOT_INVOCATIONS`; слушатель режима разработки пропускается, поэтому boot-методы не выполняются дважды и боевой запуск не сканирует исходный код.

Подробнее: [`componenta/app`](https://github.com/componenta/app/blob/main/README.ru.md) описывает `BootContext`, `BootloaderInterface`, `BootloaderProvider` и целевые объекты загрузки; [`componenta/app-http`](https://github.com/componenta/app-http/blob/main/README.ru.md), [`componenta/app-console`](https://github.com/componenta/app-console/blob/main/README.ru.md) и [`componenta/websocket-app`](https://github.com/componenta/websocket-app/blob/main/README.ru.md) показывают загрузчики конкретных областей выполнения.

## HTTP и маршрутизация

HTTP-пресеты создают `public/index.php`, `config/routes.php` и `config/pipeline.php`. Публичная точка входа запускает `Scope::HTTP`. Файл `config/pipeline.php` описывает глобальный HTTP-конвейер приложения:

```php
$app->pipe(Componenta\Error\Http\Middleware\ErrorHandlerMiddleware::class, priority: 100);
$app->pipe(Componenta\Http\Middleware\BodyParsingMiddleware::class, priority: 100);
```

`componenta/router-app` добавляет `MatchRouteMiddleware` и `DispatchRouteMiddleware` через `RoutingBootloader` с приоритетом `50`, поэтому стартовый обработчик ошибок и разбор тела запроса выполняются до сопоставления маршрута. Свои промежуточные обработчики используют тот же механизм приоритетов: большее число выполняется раньше.

`componenta/router-app` по умолчанию использует `config/routes.php` как файл ручной регистрации маршрутов. Внутри файла доступна переменная `$routes` типа `Componenta\Http\Router\Routes`. Этот файл нужен для маршрутов, которые удобнее задавать программно: группы маршрутов, общие префиксы, общие промежуточные обработчики, общие `tokens` и `defaults`, ручные `RouteRecord` и вложенные группы.

```php
use App\Http\AdminDashboard;
use App\Http\AdminUsers;
use App\Http\Middleware\RequireAdminMiddleware;
use App\Http\Middleware\RequireAuthenticationMiddleware;

/**
 * @var \Componenta\Http\Router\Routes $routes
 */

$admin = $routes->group(
    name: 'admin',
    prefix: '/admin',
    middleware: [
        RequireAuthenticationMiddleware::class,
        RequireAdminMiddleware::class,
    ],
    tokens: ['id' => '\d+'],
);

$admin->get('dashboard', '/', AdminDashboard::class);
$admin->get('users.show', '/users/{id}', AdminUsers::class);
```

Группа добавляет префикс к имени и пути маршрута. В примере итоговые имена будут `admin.dashboard` и `admin.users.show`, а пути - `/admin` и `/admin/users/{id}`. Настройки группы наследуются вложенными группами и маршрутами.

Маршруты можно добавлять декларативно через `#[Route]` или вручную в `config/routes.php`. Стартовый маршрут `/` находится в `src/Welcome.php`: если выбраны шаблоны, он возвращает `templates/welcome.phtml`, иначе возвращает JSON:

```json
{"status":"ok","message":"Componenta Framework skeleton is running."}
```

Подробнее: [`componenta/router`](https://github.com/componenta/router/blob/main/README.ru.md) описывает маршрутизатор, [`componenta/router-app`](https://github.com/componenta/router-app/blob/main/README.ru.md) описывает обнаружение атрибутов маршрутов, [`componenta/app-http`](https://github.com/componenta/app-http/blob/main/README.ru.md) описывает HTTP-адаптер приложения, [`componenta/http`](https://github.com/componenta/http/blob/main/README.ru.md) описывает базовые HTTP-контракты и исключения.

## Промежуточные обработчики HTTP

Глобальные промежуточные обработчики регистрируются в `config/pipeline.php`. `HttpBootloader` подключает этот файл, а переменная `$app` реализует `HttpBootTargetInterface`. Каждый вызов `$app->pipe(...)` добавляет обработчик в общий HTTP-конвейер. Необязательный аргумент `priority` задает порядок: обработчики с большим приоритетом выполняются раньше:

```php
use App\Http\Middleware\RequestIdMiddleware;
use Componenta\Error\Http\Middleware\ErrorHandlerMiddleware;
use Componenta\Http\Middleware\BodyParsingMiddleware;

/**
 * @var \Componenta\App\Boot\Target\HttpBootTargetInterface $app
 */

$app->pipe(ErrorHandlerMiddleware::class, priority: 100);
$app->pipe(RequestIdMiddleware::class, priority: 100);
$app->pipe(BodyParsingMiddleware::class, priority: 100);
```

Порядок важен: промежуточные обработчики выполняются по приоритету, а определения с одинаковым приоритетом сохраняют порядок регистрации. Обработчик ошибок обычно ставится раньше остальных, чтобы перехватывать исключения из следующих слоев. `BodyParsingMiddleware` должен быть до обработчиков, которым нужен `#[MapRequestPayload]`, потому что он заполняет разобранное тело PSR-7 запроса.

Базовый HTTP-пресет подключает `ErrorHandlerMiddleware` и `BodyParsingMiddleware`. Дополнительные пакеты фреймворка дают готовые обработчики: `CorsMiddleware`, `CsrfMiddleware`, `ThrottleMiddleware` и `TrustedProxyMiddleware`. Их можно ставить в глобальный конвейер или на конкретные группы и маршруты, если пакет установлен и его провайдер подключен Composer-плагином. Классы `App\Http\Middleware\...` в примерах ниже - это прикладные PSR-15 обработчики, которые создаются в проекте.

Групповые промежуточные обработчики задаются при регистрации группы в `config/routes.php`. Они применяются ко всем маршрутам группы и наследуются вложенными группами:

```php
use App\Http\AdminDashboard;
use App\Http\AdminUsers;
use App\Http\Middleware\RequireAdminMiddleware;
use App\Http\Middleware\RequireAuthenticationMiddleware;

/**
 * @var \Componenta\Http\Router\Routes $routes
 */

$admin = $routes->group(
    name: 'admin',
    prefix: '/admin',
    middleware: [
        RequireAuthenticationMiddleware::class,
        RequireAdminMiddleware::class,
    ],
);

$admin->get('dashboard', '/', AdminDashboard::class);
$admin->get('users.show', '/users/{id}', AdminUsers::class);
```

Промежуточные обработчики отдельного маршрута задаются в `#[Route]` через параметр `middlewares` или вручную через `RouteRecord`. Маршрутные обработчики добавляются после обработчиков группы:

```php
namespace App\Http;

use App\Http\Middleware\AuditPostAccessMiddleware;
use Componenta\Http\Router\Attribute\Route;

final class PostController
{
    #[Route(
        name: 'posts.show',
        path: '/posts/{id}',
        methods: 'GET',
        middlewares: [AuditPostAccessMiddleware::class],
        tokens: ['id' => '\d+'],
        group: 'api',
    )]
    public function show(): array
    {
        return ['status' => 'ok'];
    }
}
```

```php
use App\Http\PostController;
use App\Http\Middleware\AuditPostAccessMiddleware;
use Componenta\Http\Router\RouteRecord;

/**
 * @var \Componenta\Http\Router\Routes $routes
 */

$routes->addRoute(RouteRecord::get(
    name: 'posts.show',
    path: '/posts/{id}',
    handler: [PostController::class, 'show'],
    middlewares: [AuditPostAccessMiddleware::class],
    tokens: ['id' => '\d+'],
));
```

Определение промежуточного обработчика разрешает `componenta/middleware-factory`. Обычно используется имя класса из контейнера. Также поддерживаются готовые объекты `MiddlewareInterface`, `RequestHandlerInterface`, `MiddlewareGroup` и вызываемые обработчики, если они разрешаются установленными резолверами. Простые строки вроде `'auth'` не являются встроенным реестром именованных обработчиков: если приложению нужны такие имена, нужно добавить собственный резолвер или использовать классы напрямую.

Подробнее: [`componenta/app-http`](https://github.com/componenta/app-http/blob/main/README.ru.md) описывает `config/pipeline.php`, [`componenta/middleware-factory`](https://github.com/componenta/middleware-factory/blob/main/README.ru.md) описывает разрешение определений в PSR-15 обработчики, [`componenta/router`](https://github.com/componenta/router/blob/main/README.ru.md) описывает порядок применения обработчиков группы и маршрута. Конкретные реализации описаны в README middleware-пакетов: [`componenta/http-body-parsing-middleware`](https://github.com/componenta/http-body-parsing-middleware/blob/main/README.ru.md), [`componenta/http-cors-middleware`](https://github.com/componenta/http-cors-middleware/blob/main/README.ru.md), [`componenta/http-csrf-middleware`](https://github.com/componenta/http-csrf-middleware/blob/main/README.ru.md), [`componenta/http-throttle-middleware`](https://github.com/componenta/http-throttle-middleware/blob/main/README.ru.md) и [`componenta/http-trusted-proxy-middleware`](https://github.com/componenta/http-trusted-proxy-middleware/blob/main/README.ru.md).

## Атрибут `#[Route]`

`#[Route]` можно ставить на вызываемый класс или метод контроллера. Атрибут описывает имя маршрута, путь, HTTP-методы, промежуточные обработчики, ограничения параметров, значения по умолчанию, имя группы и приоритет.

```php
namespace App\Http;

use Componenta\DI\Attribute\RequestAttribute;
use Componenta\Http\Router\Attribute\Route;

final class PostController
{
    #[Route(
        name: 'posts.show',
        path: '/posts/{id:\\d+}',
        methods: 'GET',
        group: 'api',
        priority: 20,
    )]
    public function show(#[RequestAttribute] int $id): array
    {
        return ['id' => $id];
    }
}
```

Параметры `methods` принимают строку (`'GET'`), строку с разделителем (`'GET|POST'`) или массив (`['GET', 'POST']`). `middlewares` принимает строку или массив. `tokens` задает регулярные ограничения параметров пути, `defaults` задает значения по умолчанию.

Ограничения параметров можно задавать и прямо в шаблоне пути. Например, `/posts/{id:\d+}` и `/archive/[?year:\d+=2026]` задают токен маршрута внутри пути. Явный массив `tokens` имеет приоритет над ограничением внутри пути, если указаны оба варианта.

`priority` управляет порядком регистрации атрибутных маршрутов: большее значение регистрируется раньше и будет сопоставлено раньше при пересекающихся шаблонах. Это важно для конфликтов вроде `/{slug}` и `/archive`.

Если у `#[Route]` указан `group`, группа должна быть явно зарегистрирована в `config/routes.php` до финализации атрибутных маршрутов:

```php
/**
 * @var \Componenta\Http\Router\Routes $routes
 */

use App\Http\Middleware\RequireAuthenticationMiddleware;

$routes->group('api', '/api');
$routes->group('admin', '/admin', middleware: [RequireAuthenticationMiddleware::class]);
```

Если группа не зарегистрирована, маршрут не получает префикс, промежуточные обработчики, токены и значения по умолчанию этой группы: он будет добавлен как обычный маршрут с сохраненным именем группы в записи маршрута. Поэтому группы, на которые ссылаются атрибуты маршрутов, должны быть описаны явно в `config/routes.php`.

Подробнее: [`componenta/router`](https://github.com/componenta/router/blob/main/README.ru.md) описывает `RouteRecord`, `Routes` и `RouteGroup`, [`componenta/router-app`](https://github.com/componenta/router-app/blob/main/README.ru.md) описывает `AttributeRouteLocator`.

## Маппинг HTTP-запроса

`#[Route]` только сопоставляет URL с обработчиком. Параметры пути вроде `{id}` попадают в атрибуты PSR-7 запроса, но не передаются в аргументы метода автоматически по имени. Чтобы получить данные из запроса, параметр обработчика должен иметь атрибут маппинга из `componenta/di`.

Для одного значения используйте точечные атрибуты:

```php
namespace App\Http;

use Componenta\DI\Attribute\PayloadParam;
use Componenta\DI\Attribute\QueryParam;
use Componenta\DI\Attribute\RequestAttribute;
use Componenta\Http\Router\Attribute\Route;

final class PostController
{
    #[Route('posts.show', '/posts/{id}', 'GET', tokens: ['id' => '\d+'])]
    public function show(
        #[RequestAttribute] int $id,
        #[QueryParam(default: false, cast: 'bool')] bool $preview,
    ): array {
        return ['id' => $id, 'preview' => $preview];
    }

    #[Route('posts.rename', '/posts/{id}/rename', 'POST', tokens: ['id' => '\d+'])]
    public function rename(
        #[RequestAttribute] int $id,
        #[PayloadParam] string $title,
    ): array {
        return ['id' => $id, 'title' => $title];
    }
}
```

Основные атрибуты одного значения:

| Атрибут | Источник |
|---|---|
| `#[RequestAttribute]` | Атрибуты PSR-7 запроса. Сюда попадают параметры маршрута. |
| `#[QueryParam]` | Query string: `?page=2`. |
| `#[PayloadParam]` | Разобранное тело запроса. Для JSON и форм, не разобранных PHP нативно, нужен `BodyParsingMiddleware`; HTTP-пресет уже подключает его. |
| `#[Header]` | HTTP-заголовок. |
| `#[Cookie]` | Cookie. |
| `#[UploadedFile]` | Загруженный файл из `$request->getUploadedFiles()`. |

Если имя не указано, `RequestAttribute`, `QueryParam` и `PayloadParam` используют имя параметра метода. Поэтому `#[PayloadParam] string $title` читает поле `title`, а `#[RequestAttribute] int $id` читает атрибут запроса `id`. Явное имя нужно, когда имя HTTP-поля отличается от имени аргумента: `#[PayloadParam('post_title')] string $title`. Для значений из query string и тела запроса часто нужен `cast`, потому что исходные данные приходят строками. Параметры маршрута маршрутизатор уже приводит к `int` или `float`, когда значение выглядит как число.

Для DTO или массива используйте `Map*`-атрибуты. Они извлекают массив данных, применяют `map`, `cast`, `defaults`, `sortMap`, `exclude`, валидируют DTO при наличии валидатора и создают объект через контейнер:

```php
namespace App\Http;

use Componenta\DI\Attribute\MapQueryString;
use Componenta\DI\Attribute\RequestAttribute;
use Componenta\DI\Attribute\MapRequestPayload;
use Componenta\Http\Router\Attribute\Route;

final readonly class PostListQuery
{
    public function __construct(
        public int $page = 1,
        public ?string $tag = null,
    ) {}
}

final class MapPostListQuery extends MapQueryString
{
    protected array $cast = ['page' => 'int'];
    protected array $defaults = ['page' => 1];
}

final readonly class CreatePostCommand
{
    public function __construct(
        public string $title,
        public string $body,
    ) {}
}

final class PostController
{
    #[Route('posts.index', '/posts', 'GET')]
    public function index(#[MapPostListQuery] PostListQuery $query): array
    {
        return ['page' => $query->page, 'tag' => $query->tag];
    }

    #[Route('posts.create', '/posts', 'POST')]
    public function create(#[MapRequestPayload] CreatePostCommand $command): array
    {
        return ['title' => $command->title];
    }
}
```

Подробнее: [`componenta/di`](https://github.com/componenta/di/blob/main/README.ru.md) описывает маппинг HTTP-запроса, атрибуты `Map*`, кастеры и валидацию DTO; [`componenta/router-app`](https://github.com/componenta/router-app/blob/main/README.ru.md) описывает, как обработчик маршрута выполняется через DI-перехватчик.

## Перехватчики

Перехватчики - это цепочка вокруг любого PHP callable: контроллера, обработчика, сервиса или функции. Они нужны для сквозной логики, которую не хочется размазывать по бизнес-коду: логирование, метрики, транзакции, авторизация, кэширование, нормализация параметров, сериализация результата, преобразование ответа и обработка исключений.

Базовый пакет `componenta/interceptor` содержит слой времени выполнения:

| Компонент | Назначение |
|---|---|
| `InterceptorInterface` | Контракт одного перехватчика. Получает `CallableContextInterface` и `ContextHandlerInterface`. |
| `InterceptingExecutor` | Исполнитель вызываемого обработчика с цепочкой перехватчиков. Реализует `CallableExecutorInterface` и `PipelineInterface`. |
| `AttributeInterceptor` | Читает атрибуты перехватчиков у вызываемого обработчика и добавляет объявленные слои в цепочку. |
| `ParameterResolvingInterceptor` | Разрешает параметры вызываемого обработчика через DI до запуска следующих перехватчиков. |
| `CallbackInterceptorFactory` | Создает перехватчики из замыканий: `before()`, `after()`, `catch()`, `finally()`, `around()`. |
| `#[Intercept]` | Атрибут метода или функции, который подключает класс перехватчика с параметрами конструктора. |
| `ScopedInterface` и `Scope` | Ограничивают перехватчик областью выполнения: HTTP, CONSOLE, GRPC, QUEUE или WEBSOCKET. |

HTTP-пресеты генерируют `src/ConfigProvider.php` с глобальной цепочкой HTTP-перехватчиков:

```php
use Componenta\Interceptor\AttributeInterceptor;
use Componenta\Interceptor\ConfigKey as InterceptorConfigKey;

return [
    InterceptorConfigKey::HTTP_INTERCEPTORS => [
        AttributeInterceptor::class,
    ],
];
```

Фабрика `PipelineInterface` всегда начинает цепочку с `ParameterResolvingInterceptor`. Затем она добавляет перехватчики из `InterceptorConfigKey::HTTP_INTERCEPTORS`. Поэтому параметры контроллера сначала разрешаются через DI, а затем `AttributeInterceptor` применяет атрибуты на конкретном обработчике.

Есть три основных способа подключить перехватчик:

1. Глобально в `InterceptorConfigKey::HTTP_INTERCEPTORS`, если слой должен работать для всех HTTP-обработчиков.
2. Через `#[Intercept(SomeInterceptor::class, ['option' => 'value'])]`, когда сам атрибут только описывает, какой сервис-перехватчик нужно создать через фабрику атрибутов.
3. Через атрибут, который сам реализует `InterceptorInterface`. Такой атрибут создается PHP-механизмом атрибутов и сам выполняет `intercept()`. Это подходит для легких статeless-атрибутов, например `#[Paginate]`.

Пример атрибута `#[Intercept]`:

```php
use Componenta\DI\Attribute\RequestAttribute;
use Componenta\Interceptor\Attribute\Intercept;

final class UserController
{
    #[Intercept(LogCallInterceptor::class, ['channel' => 'http'])]
    public function show(#[RequestAttribute] int $id): User
    {
        // ...
    }
}
```

Готовые пакеты перехватчиков можно использовать как специализированные атрибуты:

```php
use Componenta\DI\Attribute\MapQueryString;
use Componenta\DI\Attribute\RequestAttribute;
use Componenta\Http\Router\Attribute\Route;
use Componenta\Interceptor\Http\Attribute\Respond;
use Componenta\Interceptor\Http\Paginate;
use Componenta\Interceptor\Serialization\Attribute\Serialize;
use Componenta\Stdlib\PaginatorInterface;

final class PostController
{
    #[Route('posts.show', '/posts/{id}', 'GET')]
    #[Respond(200, 'application/json')]
    #[Serialize(context: ['groups' => ['post:public']])]
    public function show(#[RequestAttribute] int $id): PostView
    {
        // ...
    }

    #[Route('posts.index', '/posts', 'GET')]
    #[Respond(200, 'application/json')]
    #[Paginate]
    public function index(#[MapQueryString] PostListQuery $query): PaginatorInterface
    {
        // ...
    }
}
```

`#[Serialize]` из `componenta/serialize-interceptor` сериализует результат через Symfony Serializer. `#[Respond]` и `#[Created]` из `componenta/http-respond-interceptor` превращают результат обработчика в PSR-7 ответ через `Componenta\Http\Responder`. `#[Paginate]` из `componenta/http-paginate-interceptor` является прямым атрибутом-перехватчиком: если обработчик вернул `PaginatorInterface`, он оборачивает его в `ResourcePaginator` и строит ссылки `prev`/`next` из текущего PSR-7 запроса.

Атрибуты выполняются как слои снаружи внутрь: верхний атрибут становится внешним слоем, нижний ближе к телу метода. Перехватчик может вызвать `$handler->handle($context)`, изменить контекст или результат, поймать исключение либо остановить цепочку и вернуть результат без вызова исходного обработчика.

`componenta/interceptor-app` не выполняет перехватчики. Он компилирует атрибуты перехватчиков в карту для сборочного кеша, чтобы в боевом режиме не перечитывать атрибуты рефлексией на каждом запросе.

Подробнее: [`componenta/interceptor`](https://github.com/componenta/interceptor/blob/main/README.ru.md) описывает выполнение перехватчиков, [`componenta/interceptor-app`](https://github.com/componenta/interceptor-app/blob/main/README.ru.md) описывает интеграцию со сборочным кешем, [`componenta/serialize-interceptor`](https://github.com/componenta/serialize-interceptor/blob/main/README.ru.md), [`componenta/http-respond-interceptor`](https://github.com/componenta/http-respond-interceptor/blob/main/README.ru.md) и [`componenta/http-paginate-interceptor`](https://github.com/componenta/http-paginate-interceptor/blob/main/README.ru.md) описывают готовые атрибуты, а [`componenta/router-app`](https://github.com/componenta/router-app/blob/main/README.ru.md) описывает HTTP-интеграцию обработчиков маршрутов с перехватчиками.

## Консольные команды

CLI-пресет и остальные пресеты с `componenta/app-console` используют `bin/console.php`. Команды собираются в общем ключе конфигурации `Componenta\App\Console\ConfigKey::COMMANDS`. Пакеты добавляют свои команды через провайдеры конфигурации, а приложение добавляет собственные команды в `config/console.php`:

```php
use App\Console\ImportPostsCommand;
use Componenta\App\Console\ConfigKey as ConsoleConfigKey;

return [
    ConsoleConfigKey::COMMANDS => [
        ImportPostsCommand::class,
    ],
];
```

В режиме разработки команды внутри директорий обнаружения также можно помечать Symfony-атрибутом `#[AsCommand]`. Обнаружение атрибутов используется только как удобство разработки; сборка для боевого режима использует собранный конфиг `console.commands`.

Также доступны стандартные команды Symfony Console, например:

```bash
php bin/console.php list
APP_ENV=development php bin/console.php app:build
php bin/console.php app:preload
php bin/console.php app:cache:clear
php bin/console.php app:cache:clear --build
php bin/console.php app:cache:clear --dev
php bin/console.php app:cache:clear --runtime
```

`app:build` нужно запускать с `APP_ENV=development` перед стартом окружения, где `APP_ENV` отличается от `development`: такие окружения читают `var/cache/build/config.cache.php`, а не пересобирают проектное определение конфигурации на каждом запросе. Команда также готовит компилируемое состояние обнаружения классов, добавленное установленными пакетами, поэтому `#[Boot]` методы, маршруты, CQRS-обработчики, карты политик и карты перехватчиков могут восстанавливаться без сканирования `src/`.

`app:preload` генерирует `var/cache/build/preload.php` из существующих артефактов сборочного кеша. Он не строит отсутствующие артефакты сам; сначала нужно выполнить `app:build`. `app:cache:clear` по умолчанию очищает сборочный кеш, кеш разработки и кеш времени выполнения; опции `--build`, `--dev` и `--runtime` ограничивают команду одной областью кеша.

Если установлен Cycle ORM, `componenta/cycle-app` добавляет `db:create`, `db:generate`, `db:schema`, `db:migrate`, `db:rollback`, `db:status` и `db:sync`. Если установлена маршрутизация, `componenta/router-app` добавляет `router:list`.

Подробнее: [`componenta/app-console`](https://github.com/componenta/app-console/blob/main/README.ru.md) описывает реестр команд, загрузчик консольной области, обнаружение команд и команды обслуживания приложения. [`componenta/cycle-app`](https://github.com/componenta/cycle-app/blob/main/README.ru.md) описывает команды базы данных. [`componenta/router-app`](https://github.com/componenta/router-app/blob/main/README.ru.md) описывает `router:list`.

## Команды и запросы приложения

Если установлен `componenta/cqrs-app`, приложение может описывать бизнес-действия как команды и запросы. Команда изменяет состояние, запрос читает данные. Их обработчики регистрируются через CQRS-пакеты и могут быть найдены через механизм обнаружения.

HTTP-контроллеры, консольные команды и другие точки входа не должны знать детали выполнения бизнес-действия. Они создают команду или запрос и передают его в соответствующую шину.

Выбранные приложением цепочки промежуточных обработчиков находятся в `src/ConfigProvider.php`. Если CQRS включен, скелет регистрирует промежуточные обработчики команд в таком порядке:

```php
use Componenta\CQRS\Command\Middleware\EventMiddleware;
use Componenta\CQRS\Command\Middleware\PolicyMiddleware as CommandPolicyMiddleware;
use Componenta\CQRS\ConfigKey as CqrsConfigKey;
use Componenta\CQRS\Query\Middleware\PolicyMiddleware as QueryPolicyMiddleware;

return [
    CqrsConfigKey::COMMAND_MIDDLEWARES => [
        CommandPolicyMiddleware::class,
        EventMiddleware::class,
    ],
    CqrsConfigKey::QUERY_MIDDLEWARES => [
        QueryPolicyMiddleware::class,
    ],
];
```

Если политики доступа не выбраны, промежуточные обработчики политик не добавляются, а цепочка запросов остается пустой. Если выбрана интеграция Cycle, установщик также добавляет `TransactionMiddleware` в цепочку команд, потому что сервис базы данных доступен. `TransportMiddleware` не ставится стандартными пресетами: добавляйте его только когда приложение настроило CQRS transport registry и serializer команд. Базовый провайдер `componenta/cqrs` специально начинает с пустых цепочек команд и запросов: приложение само выбирает нужные гарантии выполнения.

Подробнее: [`componenta/cqrs`](https://github.com/componenta/cqrs/blob/main/README.ru.md) описывает шины команд и запросов, операции, промежуточные обработчики и асинхронное выполнение; [`componenta/cqrs-app`](https://github.com/componenta/cqrs-app/blob/main/README.ru.md) описывает интеграцию CQRS с обнаружением классов и сборочным кешем.

## Политики доступа

Если установлен `componenta/policy-app`, проверки доступа описываются политиками и атрибутами политик на действиях приложения. Это позволяет держать авторизацию вне обработчиков команд и запросов.

Типовой поток такой: точка входа создает команду или запрос, промежуточный обработчик CQRS получает текущего пользователя или другого исполнителя (`actor`), `componenta/policy` проверяет политику для действия, затем выполнение передается обработчику.

Подробнее: [`componenta/policy`](https://github.com/componenta/policy/blob/main/README.ru.md) описывает политики, провайдеры и атрибуты; [`componenta/policy-app`](https://github.com/componenta/policy-app/blob/main/README.ru.md) описывает интеграцию со сборочным кешем; [`componenta/cqrs`](https://github.com/componenta/cqrs/blob/main/README.ru.md) описывает место промежуточного обработчика политик в цепочке выполнения.

## Шаблоны

Если во время установки выбран шаблонизатор, HTTP-пресет подключает `componenta/templater-app`. В проекте появляется каталог `templates/`, функция `view()` и стартовый шаблон `templates/welcome.phtml`.

Шаблоны ошибок HTTP находятся в `templates/error/`. Безопасная страница 500 должна быть доступна даже когда подробный вывод ошибок выключен.

Подробнее: [`componenta/templater`](https://github.com/componenta/templater/blob/main/README.ru.md) описывает контракты рендереров, [`componenta/templater-app`](https://github.com/componenta/templater-app/blob/main/README.ru.md) описывает функцию `view()` и интеграцию с приложением.

## WebSocket

WebSocket-пресет создает отдельную точку входа `bin/websocket.php` и запускает `Scope::WEBSOCKET`. HTTP-инфраструктура при этом не создается. Если WebSocket добавлен как дополнительная возможность к другому пресету, установщик добавляет отдельный Composer-скрипт `serve:websocket`.

Подробнее: [`componenta/websocket-server`](https://github.com/componenta/websocket-server/blob/main/README.ru.md) описывает базовый сервер сокетов, [`componenta/websocket-app`](https://github.com/componenta/websocket-app/blob/main/README.ru.md) описывает интеграцию WebSocket-области в приложение.

## Доступные команды и Composer-скрипты

Набор скриптов зависит от выбранного пресета:

| Команда | Когда доступна | Назначение |
|---|---|---|
| `composer serve` | HTTP-пресеты | Запускает встроенный PHP-веб-сервер на `localhost:8000` с корнем `public/`. |
| `composer serve:websocket` | WebSocket-пресет или WebSocket-дополнение | Запускает `bin/websocket.php`. |
| `composer test` | Всегда | Запускает выбранный тестовый фреймворк: Pest или PHPUnit. |
| `composer analyse` | Всегда | Запускает PHPStan по каталогам, созданным выбранным пресетом. |
| `php bin/console.php list` | Если установлен `componenta/app-console` | Показывает доступные консольные команды. |
| `APP_ENV=development php bin/console.php app:build` | Если установлен `componenta/app-console` | Собирает кеш конфигурации, контейнера и компилируемого состояния обнаружения классов для окружений вне режима разработки. |
| `php bin/console.php app:preload` | Если установлен `componenta/app-console` | Генерирует preload-файл из артефактов сборочного кеша. |
| `php bin/console.php app:cache:clear [--build\|--dev\|--runtime]` | Если установлен `componenta/app-console` | Очищает все каталоги кеша приложения или только выбранную область кеша. |
| `php bin/console.php router:list` | Если установлена HTTP-маршрутизация | Показывает зарегистрированные маршруты. |

CLI-пресет не создает `public/`, `config/routes.php`, `config/pipeline.php` и WebSocket-файлы. HTTP-пресеты создают `config/routes.php` и подключают загрузчик маршрутизации через установленные пакеты.

Подробнее: [`componenta/app-console`](https://github.com/componenta/app-console/blob/main/README.ru.md) описывает CLI-слой, [`componenta/router-app`](https://github.com/componenta/router-app/blob/main/README.ru.md) описывает обнаружение HTTP-маршрутов.

## Структура проекта

| Путь | Назначение |
|---|---|
| `.env` | Локальное окружение. Создается из `.env.dist`. |
| `config/config.php` | Главная декларация провайдеров и обнаружения классов. |
| `config/container.php` | Сборка контейнера приложения. |
| `config/componenta-providers.php` | Сгенерированный список провайдеров установленных пакетов. Появляется после работы `componenta/composer-plugin`. |
| `config/autoload/` | Проектная конфигурация из `*.global.*` и `*.local.*`. |
| `src/` | Код приложения в пространстве имен `App\`. |
| `bin/` | CLI-точка входа. |
| `public/` | HTTP-точка входа. Есть только у HTTP-пресетов. |
| `templates/` | Шаблоны приложения и ошибок. Есть только если выбраны шаблоны или нужна безопасная HTTP-страница ошибки. |
| `var/cache/dev/` | Кеши режима разработки. |
| `var/cache/build/` | Сборочный кеш для окружений вне `development`. |
| `var/cache/runtime/` | Кеши времени выполнения приложения. |
| `log/` | Логи приложения. |
| `storage/` | Файлы приложения. |

Подробнее: [`componenta/path-resolver`](https://github.com/componenta/path-resolver/blob/main/README.ru.md) описывает разрешение путей от корня проекта, [`componenta/app`](https://github.com/componenta/app/blob/main/README.ru.md) описывает структуру кеша.

## Связанные пакеты

- [`componenta/app`](https://github.com/componenta/app/blob/main/README.ru.md) - жизненный цикл приложения, области выполнения, конфигурация, контейнер, кеши и загрузчики.
- [`componenta/app-http`](https://github.com/componenta/app-http/blob/main/README.ru.md) - HTTP-адаптер приложения.
- [`componenta/app-console`](https://github.com/componenta/app-console/blob/main/README.ru.md) - консольный рантайм и команды.
- [`componenta/composer-plugin`](https://github.com/componenta/composer-plugin/blob/main/README.ru.md) - генерация `config/componenta-providers.php`.
- [`componenta/config`](https://github.com/componenta/config/blob/main/README.ru.md) - провайдеры конфигурации и загрузчики файлов.
- [`componenta/di`](https://github.com/componenta/di/blob/main/README.ru.md) - контейнер, фабрики и атрибуты внедрения зависимостей.
- [`componenta/router`](https://github.com/componenta/router/blob/main/README.ru.md) и [`componenta/router-app`](https://github.com/componenta/router-app/blob/main/README.ru.md) - маршрутизация и обнаружение маршрутов.
- [`componenta/cqrs`](https://github.com/componenta/cqrs/blob/main/README.ru.md) и [`componenta/cqrs-app`](https://github.com/componenta/cqrs-app/blob/main/README.ru.md) - команды, запросы и их обнаружение.
- [`componenta/policy`](https://github.com/componenta/policy/blob/main/README.ru.md) и [`componenta/policy-app`](https://github.com/componenta/policy-app/blob/main/README.ru.md) - политики доступа и интеграция со сборочным кешем.
- [`componenta/templater`](https://github.com/componenta/templater/blob/main/README.ru.md) и [`componenta/templater-app`](https://github.com/componenta/templater-app/blob/main/README.ru.md) - шаблоны и функция `view()`.
- [`componenta/error-handler`](https://github.com/componenta/error-handler/blob/main/README.ru.md) и [`componenta/error-handler-app`](https://github.com/componenta/error-handler-app/blob/main/README.ru.md) - обработка ошибок и безопасный HTTP-рендеринг.
- [`componenta/websocket-server`](https://github.com/componenta/websocket-server/blob/main/README.ru.md) и [`componenta/websocket-app`](https://github.com/componenta/websocket-app/blob/main/README.ru.md) - WebSocket-сервер и интеграция в приложение.
