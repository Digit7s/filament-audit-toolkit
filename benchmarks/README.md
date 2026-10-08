# Optional benchmarks

Run the disposable SQLite Filament query benchmark with:

```bash
AUDIT_BENCHMARK_EVENTS=1000 composer benchmark
```

The benchmark measures one paginated Audit Explorer query against synthetic data and reports wall time and peak memory. It is a SQLite baseline only, not a cross-database performance claim.
