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
├── config/                 # Default configurations
├── information/            # Ratings, metadata
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
1. Create class in `src/Component/Provider/` extending `AbstractProvider`
2. Implement `constructEPG(string $channel, string $date): Channel|bool`
3. Add channel list JSON in `resources/channel_config/channels_yourprovider.json`
4. Set priority in constructor (compare with existing providers)
5. Use `$this->getContentFromURL()` for HTTP requests (auto-cached)
6. Class name must match filename

### Cache System
**Two-layer caching:**
1. **EPG Cache** (`var/cache/`):
   - Individual XML files per channel/date: `{channel}_{date}.xml`
   - Controlled by `fetch_policies` in config (cache-first, network-first, cache-only)
   - Validity checked via `min_endtime` (last program must end after 23h30 of same day)
   - Retained for `cache_ttl` days (default 8)

2. **HTTP Cache** (`var/provider_cache/`):
   - Caches provider HTTP responses via `ProviderCache`
   - Cache key: MD5 of URL + headers
   - Transparent caching for `getContentFromURL()` calls

**Cache policies (fetch_policies):**
- `cache-only`: Only read from EPG cache, skip if missing
- `cache-first`: Try EPG cache first, fetch from provider if unavailable
- `network-first`: Always fetch fresh from provider (typical for today)

### Testing
- Tests in `tests/` with PHPUnit 9.5
- Integration tests validate generated XML structure
- Hook `AllTestsPassedHook` runs after successful test runs
- Mock providers in `tests/Ressources/`

### Code Quality
- PHP CS Fixer for style (`.php-cs-fixer.php`)
- PHPStan level 4 for static analysis
- PSR-4 autoloading with namespace `racacax\XmlTv`

### Docker
- Multi-stage build in `Dockerfile`
- Volume mounts for config and output
- Runs `php manager.php export` by default

## Requirements
- PHP >=8.0
- Extensions: curl, zip, mbstring, xml, json, dom, simplexml, xmlreader, libxml, pcntl, posix, intl
- Composer for dependencies
