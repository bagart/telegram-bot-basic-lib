# BAGArt/TelegramBotBasic

## Test Coverage

> Generated: 2026-03-18 | 131 tests | 229 assertions

### Overall Coverage

```
██████████░░░░░░░░░░░░░░░░░░░░░░░░░░░░  20.2%
```

### Coverage by Folder (excluding TgApi/*)

| Folder | Coverage | Visual |
|--------|----------|--------|
| **Extra** | `98.0%` | `███████████████████████████████████████░` |
| **TelegramBotServiceProvider** | `88.3%` | `████████████████████████████████████░░░░` |
| **TgApiServices/TgEntityToDTORegistry** | `81.0%` | `██████████████████████████████████░░░░░░` |
| **Exceptions** | `71.4%` | `██████████████████████████░░░░░░░░░░░░░░` |
| **Wrappers/TgBotLogWrapper** | `64.7%` | `██████████████████████████░░░░░░░░░░░░░░` |
| **TgApiServices/TgApiDTOMapper** | `59.2%` | `██████████████████████░░░░░░░░░░░░░░░░░░` |
| **Wrappers/TgBotCacheWrapper** | `51.6%` | `████████████████████░░░░░░░░░░░░░░░░░░░░` |
| **Commands/Traits** | `45.7%` | `██████████████████░░░░░░░░░░░░░░░░░░░░░░` |
| **Commands** | `0.0%` | `░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░` |
| **DevTool** | `0.0%` | `░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░` |
| **ApiCommunication** | `0.0%` | `░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░` |

### File-Level Detail

```
TelegramBotBasic/
├── Extra/
│   └── TinyFileCache .................. 98.0%  ✅
├── TgApiServices/
│   ├── TgEntityToDTORegistry ......... 81.0%  ✅
│   └── TgApiDTOMapper ................ 59.2%  🟡
├── Commands/
│   ├── Traits/
│   │   └── ArtisanExtraTrait ......... 45.7%  🟡
│   ├── Demo/DemoSendPollCommand ......  0.0%  ⬜
│   ├── TgDevDTOActualizeCommand ......  0.0%  ⬜
│   ├── TgPollerCommand ...............  0.0%  ⬜
│   ├── TgWhoamiCommand ...............  0.0%  ⬜
│   ├── TgPollerCommand ...............  0.0%  ⬜
│   └── WebhookCommand ................  0.0%  ⬜
├── DevTool/
│   └── DTOGenerator ..................  0.0%  ⬜
├── Http/Controllers/
│   └── WebhookController .............  ⚠️ tested via Feature tests
├── Routes
│   └── routes.php ....................  0.0%  ⬜
└── RawExamples/ ......................  0.0%  ⬜ (examples only)

TelegramBot/
├── TelegramBotServiceProvider ........ 88.3%  ✅
├── Exceptions/
│   ├── TgUnregisteredEntityNameException 71.4%  ✅
│   └── TgApiUserBreakeException ......  0.0%  ⬜
├── TgApiServices/
│   ├── TgEntityToDTORegistry ......... 81.0%  ✅
│   ├── TgApiDTOMapper ................ 59.2%  🟡
│   ├── TgEntityNamer .................  ⚠️ tested
│   ├── TgApiResponse .................  ⚠️ tested
│   └── TgApiProperty .................  ⚠️ tested
├── Wrappers/
│   ├── TgBotLogWrapper ............... 64.7%  🟡
│   └── TgBotCacheWrapper ............. 51.6%  🟡
├── ApiCommunication/
│   ├── ClientServices/
│   │   ├── TgCircuitBreaker ..........  ⚠️ tested
│   │   ├── TgRateLimiter .............  ⚠️ tested
│   │   └── TgRetryPolicy .............  ⚠️ tested
│   ├── TgBotApiClient ................  0.0%  ⬜
│   ├── TgBotApiDTOClient .............  0.0%  ⬜
│   └── TgBotApiReturnParser ..........  0.0%  ⬜
└── TgApi/* ...........................  ❌ excluded from report
```

> ⚠️ = has tests but coverage tool didn't detect (likely namespace/directory mismatch)
> ✅ = high coverage | 🟡 = partial | ⬜ = not tested

### Test Files (25 total)

**Feature Tests (3):**
- `WebhookControllerTest.php` — webhook parsing, echo mode, error handling
- `TgLibUpdaterTest.php` — npm update command execution
- `TgSchemaPreparerTest.php` — node.js schema script execution

**Unit Tests (22):**
- `TinyFileCacheTest.php` — PSR-16 cache (21 tests)
- `TgApiDTOMapperTest.php` — DTO ↔ array conversion (7 tests)
- `TgEntityToDTORegistryTest.php` — entity registration/lookup (6 tests)
- `TgEntityToDTORegistryFactoryTest.php` — factory creation (2 tests)
- `TgBotLogWrapperTest.php` — log delegation (6 tests)
- `TgBotCacheWrapperTest.php` — cache delegation (7 tests)
- `TgCircuitBreakerTest.php` — circuit breaker logic (6 tests)
- `TgRateLimiterTest.php` — rate limiting (5 tests)
- `TgRetryPolicyTest.php` — retry decisions (9 tests)
- `ExceptionTest.php` — exception construction (4 tests)
- `TgEntityNamerTest.php` — entity naming (11 tests)
- `WebhookTest.php` — webhook get/set/delete (5 tests)
- `ArtisanExtraTraitTest.php` — option preparation (7 tests)
- `TokenResolverTraitTest.php` — token format validation (7 tests)
- `TgApiResponseTest.php` — response DTO (2 tests)
- `TgApiPropertyTest.php` — property metadata (2 tests)
- `ServiceProviderTest.php` — provider instantiation (1 test)
- `MethodsDTOTest.php` — SendMessage/GetMe DTOs (2 tests)
- `TypesDTOTest.php` — User/Chat DTOs (2 tests)
- `EnumTest.php` — ChatPropType/SendPoll enums (3 tests)

### Running Tests

```bash
# All TelegramBot tests
php vendor/bin/pest tests/Unit/TelegramBotBasic tests/Feature/TelegramBotBasic tests/Unit/TelegramBot

# With coverage
php -d xdebug.mode=coverage vendor/bin/pest tests/Unit/TelegramBot tests/Unit/TelegramBotBasic tests/Feature/TelegramBotBasic --coverage
```

---

## To use without Laravel:

```bash
composer require psr/simple-cache
composer require monolog/monolog

export TELEGRAM_BOT_TOKEN='***:***'
php vendor/BAGArt/TelegramBotBasic/RawExamples/GetUpdateDTOWithPollerExample.php
php vendor/BAGArt/TelegramBotBasic/RawExamples/GetUpdateWithWebhookEmulateExample.php
```
