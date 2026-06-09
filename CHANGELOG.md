# Changelog

All notable changes to `mostafax/dynamic-hybrid-reporting-engine` are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2026-06-10

### Fixed
- Ship the missing `Contracts\ReportEngineInterface` contract. `ReportEngine`
  declared `implements ReportEngineInterface` but the interface file was never
  committed, causing a fatal `Interface "...ReportEngineInterface" not found`
  on a clean install — the engine (`run()` / `execute()` / `prepare()`) and every
  REST route failed to boot.

### Added
- Bind the engine to its contract: `ReportEngineInterface` is now aliased to
  `ReportEngine` in the service provider, so it can be type-hinted via the
  interface for dependency injection.

[0.1.0]: https://github.com/mostafax/dynamic-hybrid-reporting-engine/releases/tag/v0.1.0
