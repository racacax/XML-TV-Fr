# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

XML TV Fr is a PHP application that fetches TV program guides from multiple sources (providers) and generates XMLTV formatted files. It supports parallel fetching from 30+ providers (MyCanal, Orange, Telerama, etc.) and outputs compressed XML files.

## Commands

### Development Commands
```bash
# Run all quality checks (cs-fix, phpstan, tests)
make quality

# Fix code style issues
make cs-fix

# Run static analysis
make phpstan

# Run tests
make test

# Run PHPUnit directly with options
bin/phpunit --filter=TestName
```

### Main Application Commands
```bash
# Generate TV guide (main command)
php manager.php export

# Skip EPG generation, only export
php manager.php export --skip-generation

# Keep cache after generation
php manager.php export --keep-cache

# Fetch specific channel
php manager.php fetch-channel <channel-id> <date>

# Update default channel logos
php manager.php update-default-logos

# Show help
php manager.php help
```

### Docker
```bash
# Build image
docker build -t xmltvfr .

# Run export
docker run -v ./var/export:/app/var/export -v ./config/:/app/config xmltvfr

# Development run with custom args
make drun ARGS="php manager.php export --skip-generation"
```

## Architecture

### Provider System
The core of the application is a provider-based architecture where each TV guide source is a separate Provider class.

**Key concepts:**
- All providers implement `ProviderInterface` and extend `AbstractProvider`
- Each provider has a priority (0-1 float) that determines fallback order
- Providers are located in `src/Component/Provider/`
- Each provider has a corresponding channel list in `resources/channel_config/channels_*.json`
- Providers fetch EPG data and convert it to Channel/Program value objects

**Provider workflow:**
1. `constructEPG(channel, date)` is called for each channel/date combination
2. Provider fetches data from its source (with HTTP caching via `ProviderCache`)
3. Creates a `Channel` object and adds `Program` objects to it
4. Returns the Channel on success, or false on failure
5. If provider fails, next provider in priority order is tried

**Priority system:**
- Global priority defined in provider constructor (e.g., Telerama=0.8, Orange=0.6)
- Can be overridden per-channel in `config/channels.json` via "priority" field
- Can be modified globally via `priority_orders` in config
- Higher priority = tried first (0.5 before 0.2)

### Configuration System
Configuration is managed via `Configurator` class which reads from `config/config.json`:

**Current config structure (v4):**
```json
{
  "fetch_policies": {
    "cache-first": [1,2,3,4,5,6,7],    // Days to fetch (cache first, network fallback)
    "network-first": [0],               // Days to always fetch fresh (today)
    "cache-only": [-2,-1]               // Days to only use cache (yesterday, 2 days ago)
  },
  "cache_ttl": 8,                       // Cache retention in days (How many days until it is considered obsolete)
  "cache_physical_ttl": 8,              // Cache retention in days (on disk)
  "output_path": "var/export/",
  "time_limit": null,                   // Script execution time limit (null = unlimited)
  "memory_limit": -1,                   // Memory limit (-1 = unlimited)
  "export_handlers": [                  // Output format handlers
    {"class": "GZExport", "params": {}},
    {"class": "ZIPExport", "params": {}}
  ],
  "delete_raw_xml": false,              // Delete uncompressed XML after export
  "enable_dummy": false,                // Show dummy EPG when channel not found
  "priority_orders": {},                // Override provider priorities globally
  "guides": [                           // List of XMLTV files to generate
    {"channels": ["config/channels.json"], "filename": "xmltv"}
  ],
  "nb_threads": 1,                      // Parallel threads (requires shell access)
  "ui": "MultiColumnUI"                 // MultiColumnUI or ProgressiveUI
}
```

**Key concepts:**
- **fetch_policies**: Defines which days to fetch and cache behavior
  - Day offsets: 0=today, 1=tomorrow, -1=yesterday, etc.
  - `cache-first`: Try cache first, fetch from network if unavailable
  - `network-first`: Always fetch fresh from network (ignores cache)
  - `cache-only`: Only use existing cache (no network requests)
- **export_handlers**: Array of export classes (GZExport, ZIPExport, CommandLineExport)
- **guides**: Array of output files, each with array of channel config files
- **min_endtime** (optional): Minimum end time to consider cache full (default 84600, 23h30 of same day)

### Multi-threading
- Implemented via Amphp parallel workers (`src/Component/MultiThreadedGenerator.php`)
- Each thread processes channels independently
- Worker communication via Amp\Sync\Channel for status updates

### Data Flow
```
Config → Generator → For each EPGDate (with cache policy)
                     → ChannelThread (per channel/date)
                        → Check cache based on fetch_policy
                        → Provider.constructEPG() (if cache miss/network-first)
                        → Channel → Program(s)
                        → CacheFile (XML storage in var/cache/)
         → XmlExporter → Merge all XMLs
         → Export handlers (GZExport, ZIPExport)
```

**Cache flow:**
1. For each date, check `fetch_policies` to determine cache behavior
2. `cache-only`: Only read from cache, skip if missing
3. `cache-first`: Try cache first, fetch from provider if unavailable/expired
4. `network-first`: Always fetch fresh from provider (used for today by default). Will fallback to cache, if available.
5. Cache validity checked via `min_endtime` and `cache_ttl`

### Value Objects
- `Channel`: Represents a TV channel with programs
- `Program`: Individual TV program with title, description, categories, etc.
- `EPGDate`: Date handling with timezone (Europe/Paris)
- `EPGEnum`: Cache state constants (NO_CACHE, EXPIRED_CACHE, PARTIAL_CACHE, FULL_CACHE)

### UI System
Two terminal UI implementations in `src/Component/UI/`:
- `MultiColumnUI`: Multi-column status display
- `ProgressiveUI`: Progressive line-by-line output

## Project Structure

```
src/
├── Component/
│   ├── Provider/           # TV guide data sources (30+ providers)
│   │   ├── AbstractProvider.php
│   │   ├── Orange.php, MyCanal.php, Telerama.php, etc.
│   ├── UI/                 # Terminal display
│   ├── Export/             # Output formats (XML, GZ, ZIP)
│   ├── Generator.php       # Main generation logic
│   ├── MultiThreadedGenerator.php
│   ├── ChannelThread.php   # Per-channel/date processing
│   ├── XmlExporter.php     # XML merging and export
│   ├── CacheFile.php       # Individual cache file handling
│   └── Utils.php           # Helper functions
├── ValueObject/            # Data models
│   ├── Channel.php
│   ├── Program.php
│   └── EPGDate.php
├── StaticComponent/        # Static utilities
└── Configurator.php        # Configuration management

resources/
├── channel_config/         # Provider channel lists (JSON)
├── config/                 # Default configurations (default_config.json, default_channels.json = default channel selection)
├── information/            # Ratings, default_channels_infos.json (default name/icon of every channel)
└── validation/             # XML validation

config/                     # User configuration (git-ignored)
├── config.json             # Main config
└── channels.json           # User's channel list

var/
├── cache/                  # Per-channel/date XML cache
├── export/                 # Final XMLTV output
└── logs/                   # JSON logs

commands/                   # CLI command implementations
tools/                      # Web UI for channel selection (legacy)
```

## Development Notes

### Adding a New Provider
1. Create class in `src/Component/Provider/` extending `AbstractProvider` and implementing `ProviderInterface` (class name must match filename; providers are auto-discovered by `Utils::getProviders()`, no registration)
2. Constructor: `__construct(Client $client, ?float $priority = null, array $extraParam = [])` calling `parent::__construct($client, ResourcePath::getInstance()->getChannelPath('channels_x.json'), $priority ?? 0.5)`
3. Implement `constructEPG(string $channel, string $date): Channel|bool`: start with `$channelObj = parent::constructEPG($channel, $date)`, return `false` if `!$this->channelExists($channel)`, filter programs with `[$minDate, $maxDate] = $this->getMinMaxDate($date)` (Paris day, compare on the *start* of programs), return `false` when there is no program
4. Add the channel list `resources/channel_config/channels_x.json` (`"ChannelID.fr": <value>`; the value can be a scalar, an object like MyCanal/SudInfo, or a list of objects like ExternalXML)
5. Optional `getLogo(string $channel): ?string` (call `parent::getLogo()` first, it throws for unknown channels): used by `php manager.php update-default-logos <Provider>`
6. Add unit tests (`tests/Unit/Component/Provider/XTest.php`, mock `GuzzleHttp\Client`) and one entry in `tests/Integration/ProvidersTest.php` (`[X::class, ['channels' => ['TF1.fr']]]`, optional `'extra_params'`). `testAllProvidersHaveBeenConsidered` fails if a provider is neither tested nor ignored. Removing a provider = delete class + channels json + test entries (+ any hook it needed in another provider)
7. `docs/providers.md` is partly outdated (describes `addProgram`, `$post`...): trust the code. Document new `extra_params` in `docs/configuration.md`

**Conventions**
- Priority (higher = tried first): Orange .95, Bouygues .9, SFR .85, MyCanal .7, Telerama .4... Official/rich sources .7, approximate or video-oriented sources .2-.3
- Option naming for `extra_params`: `<provider>_enable_details` (default `true`). A provider with one request per program must offer it
- Channel IDs: **always reuse the existing ID** (grep `resources/channel_config/*.json`, `resources/information/default_channels_infos.json` and `resources/config/default_channels.json`) even if an issue proposes a slightly different one (`FranceInfo.fr` vs `Franceinfo.fr`, `PassionNovelas.af`...). Fallback between providers only works with identical IDs. New IDs need an entry in `default_channels_infos.json` (name + icon)
- Instances are created per channel/date in worker processes (`ProviderTask`): never rely on in-memory state, use the HTTP cache

**HTTP helpers (`AbstractProvider::getContentFromURL`)**
- Signature: `(string $url, array $headers = [], bool $ignoreCache = false, bool $decodeEntities = true, ?string $body = null)`. A non-null `$body` sends a POST (JSON, form...); the cache key is `md5(url + headers + body)`, locked between threads
- Pass `$decodeEntities = false` for JSON/XML (the default `html_entity_decode` corrupts them)
- To make N channels/days share one download, send an identical request whatever the channel/day (fixed period, all channels) so the cache key is the same (OLPlay, AntenneReunion, ExternalXML, SRGSSR)
- Failures return `''` (no exception): check emptiness. Only `ConnectException` throws `NetworkConnectionException`
- Some sites deny requests without browser-like headers (Tivi5MondeUSA needs `Accept`, `Accept-Language`, `Accept-Encoding: gzip, deflate`, `Sec-Fetch-*`)

**`Program` API (`src/ValueObject/Program.php`)**: `addTitle/addSubTitle/addDesc/addCategory($value, $lang = 'fr')`, `addIcon`, `setDate`, `setCountry`, `addCredit($name, $type, $role = null)` (types: director, actor, writer, adapter, producer, composer, editor, presenter, commentator, guest; unknown types become guest, so map or skip them), `setEpisodeNum($season, $episode)` (**1-based**, writes xmltv_ns), `setRating($value, $system = 'CSA')` (`'-12'`, `'Tout public'`), `addSubtitles('teletext')`, `setAudioDescribed()`, `setPreviouslyShown()`, `setPremiere()`, `addKeyword()`, `addStarRating`, `addReview`. `Program` extends `Tag` (`new Tag($name, $value|array, $attributes)`, `addChild`), any XMLTV element can be built generically (see `ExternalXML`). Times are converted to Europe/Paris; use `new Program($start, $end)` with offset-aware `DateTimeImmutable`, or `Program::withTimestamp()`
- Existing conventions: live => keyword `direct`, sign language => keyword `langue des signes`, subtitles => `teletext`

**Providers added with notable designs** (use as models): `OLPlay` (POST JSON API, 2 shared requests), `AntenneReunion` (POST form + API key header, 1 shared request), `ExternalXML` (any XMLTV URL, gzip, list of sources per channel, `getLogo` from `<channel><icon>`), `Tivi5MondeUSA` (HTML via DOM/XPath, details optional), `SRGSSR` (official API, one request per business unit and day), `FranceTV` (video-based guide, titles rebuilt from slugs, low priority), `TracePlus::getLogo` (CMS GraphQL), `OuestFrance` and `LeTelegramme` (HTML guides giving only `HH:MM`: pages of the day before and of the day, date rollover when the time goes back, `AbstractProvider::resolveRepeatedHour()` for the October hour that exists twice; OuestFrance uses the Orange/Telerama channel ids). `MyCanal` entries: `{"id": N, "region": "fr"}`

### Logos and channel information
- `resources/information/default_channels_infos.json` holds the default name/icon of every channel (**not** `resources/config/default_channels.json`, which is the default channel selection written to `config/channels.json`). Format: 2-space JSON with readable characters; keep it that way (the update command preserves it)
- `php manager.php update-default-logos <Provider>` rewrites **all** channels known by the provider via `getLogo()`: for one channel only, use a one-off script calling `getLogo()` and write the file with the same formatting
- Prefer official logos (provider CMS/API, Wikimedia Commons PNG thumbnails `.../thumb/x/xy/File.svg/500px-File.svg.png`). Check URLs return 200 and view the image

### Investigating an issue / new source
- `gh` is not installed: read issues with `curl https://api.github.com/repos/racacax/XML-TV-Fr/issues/N` (+ `/comments`). WebFetch summaries drop details (and may invent URLs): verify every URL from an issue with curl
- Find undocumented APIs in the JS bundles of the website (grep endpoints, `post(`/`get(`, config objects such as `apiKey`, `platformCodename`, `languageId`) and test with curl; a 405 means POST, "Missing parameter" errors reveal required fields
- To check whether a channel exists on a provider, use its own list/grid endpoint (SFR guide JSON, Telerama `tv-program/grid`, Orange `programs?epgIds=a,b,c` accepts many IDs and returns `channelId`, MyCanal `epgGrid`, PlayTV `channels`, TeleLoisirs `_esi/channel-list`) or fingerprint distinctive program titles. Orange/Bouygues/Telerama/Telecablesat/SFR share a platform ID space (e.g. `RMCDecouverte.fr` is `1400` everywhere), MyCanal/Oqee/PlayTV have their own
- Timezones: always determine which times are real instants (ISO with offset, UTC `Z`) and which are local display times (e.g. tivi5mondeusa shows New York time but its `datetime` attributes are UTC). Test DST (Paris changes on the last Sunday of March/October) and prefer fixed non-DST dates in unit tests
- Creating accounts/tokens on third-party services is denied by the permission classifier: do not retry, report it to the user

### Cache System
**Two-layer caching:**
1. **EPG Cache** (`var/cache/`):
   - Individual XML files per channel/date: `{channel}_{date}.xml`
   - Controlled by `fetch_policies` in config (cache-first, network-first, cache-only)
   - Validity checked via `min_endtime` (last program must end after 23h30 of same day)
   - Retained for `cache_ttl` days (default 8)

2. **HTTP Cache** (`var/provider/`):
   - Caches provider HTTP responses via `ProviderCache`
   - Cache key: MD5 of URL + headers (+ body for POST)
   - Transparent caching for `getContentFromURL()` calls
   - Cleared at the start and at the end of `Generator::generate()` (it only lives during one run); unit tests call `ProviderCache::clearCache()` in `setUp`/`tearDown`

**Cache policies (fetch_policies):**
- `cache-only`: Only read from EPG cache, skip if missing
- `cache-first`: Try EPG cache first, fetch from provider if unavailable
- `network-first`: Always fetch fresh from provider (typical for today)

### Testing
- Tests in `tests/` with PHPUnit 9.5; `make quality` runs cs-fix, phpstan and the **unit** tests (integration tests are excluded and need network: `bin/phpunit tests/Integration/ProvidersTest.php --filter '...'`)
- Provider unit tests mock `GuzzleHttp\Client` (`createMock(Client::class)`, `get`/`post` with `willReturnCallback` keyed by URL) and build fixtures inline relative to dates; to inject a channel list, use an anonymous subclass setting `$this->channelsList` before `parent::__construct` (the file is only read when the list is empty)
- Hook `AllTestsPassedHook` regenerates `integrity.sha256` after test runs: it always shows as modified in `git status`
- `ProvidersTest` prints "Failed to configure Virgin Plus provider" in some environments (pre-existing, unrelated)
- Mock providers in `tests/Ressources/`

### Code Quality
- PHP CS Fixer for style (`.php-cs-fixer.php`), PHPStan level 4 (`make phpstan`): `DOMXPath::evaluate`, `XMLReader::expand` etc. need type guards
- PSR-4 autoloading with namespace `racacax\XmlTv`
- Config: `Configurator::initFromConfigFile` rejects 3.x configs (keys `days`, `enable_gz`, ...) with an explicit error
- Do not commit unless asked; end commit messages with the attribution line required by the session

### Docker
- Multi-stage build in `Dockerfile`
- Volume mounts for config and output
- Runs `php manager.php export` by default

## Requirements
- PHP >=8.0
- Extensions: curl, zip, mbstring, xml, json, dom, simplexml, xmlreader, libxml, pcntl, posix, intl
- Composer for dependencies
