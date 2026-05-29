# Changelog

All notable changes to `core-foundation` will be documented in this file

## 1.0.0 - 2026-05-29

- Initial open source release of `core-foundation`.
- Features robust architecture base classes: `BaseModel`, `BaseController`, `BaseService`, `BaseRepository`, `BaseRequest`, `BaseDataObject`, `BaseResource`, `BaseCollection`.
- Includes Server-Timing profiling middleware (`ProfilingMiddleware`, `ServerTimingMiddleware`).
- Adds filtering, sorting, and caching for repositories via `FilterApplicator`, `SortApplicator`, and `RepositoryCache`.
- Provides pipeline execution hooks (`HasPipeline`) and namespace-based event dispatching (`HasEvent`).
- Introduces conditional service preference capabilities (`HasFactory`).
