# Bundled bot-protection data

All files here are **auto-generated** by `bin/update-bot-data.php`. Do not edit by hand — run the updater instead.

> **Phase 1 ships bundled data only.** `SignatureRefresher`'s wp-cron-driven overlay refresh is deliberately not scheduled (see `BotLifecycle::activate`). What you see in this directory is what the classifier sees — no upstream pulls happen at runtime. Phase 2 (`DEF-42173`) re-enables cron-driven refresh, but pointed at CloudLinux-owned mirrors of the upstream sources, with a change-gate that requires human review when an incoming refresh exceeds defined deltas (IP-range count, UA-token count, checksum churn).

## Shape

Each file returns a PHP array with provenance metadata:

```php
return array(
    'source_url' => '<canonical fetch URL(s)>',
    'fetched_at' => '<ISO-8601 UTC>',
    'checksum'   => 'sha256:<hex>',
    'ranges'     => array('1.2.3.0/24', ...),        // IP range files
    // OR
    'signatures' => array('Googlebot', ...),         // UA signature files
);
```

## Source status

| Source | Mode | Endpoint |
|---|---|---|
| AWS, GCP, Azure, OVH, Hetzner, DigitalOcean | LIVE | ip-ranges.amazonaws.com / gstatic / azure-ips mirror / RIPE Stat / digitalocean |
| Cloudflare, Fastly, CloudFront | LIVE | vendor published text/JSON |
| OpenAI, Google, Bing | LIVE | vendor published JSON |
| Meta | LIVE (fallback to MANUAL) | hackertarget ASN lookup; falls back to pinned snapshot |
| ai.robots.txt UA list | LIVE | github raw |
| Apple | MANUAL | Apple publishes no finer-grained Applebot list than 17.0.0.0/8 |
| DuckDuckGo, Perplexity | MANUAL | published in docs pages (HTML, no JSON) |
| Akamai, Sucuri, Imperva, QUIC.cloud | MANUAL | customer-only or HTML-only |
| `ua-search-engines`, `ua-malicious` | MANUAL | hand-curated lists |
| `ua-rdns-suffixes` | MANUAL | provider PTR namespaces for FCrDNS verification (Yandex/Baidu/Sogou/Seznam/Naver/Mojeek) |

## Known follow-ups

- **Azure data size** — `datacenter-ip-ranges/azure.php` is ~450KB because the AzureCloud service tag aggregates every publicly-routable Azure prefix. Worth revisiting: CIDR aggregation, or narrower service tags, or moving the fat files to an on-demand overlay loaded only when a classification actually needs to verify against Azure.
- **CDN ranges still MANUAL** — Akamai, Sucuri, Imperva, QUIC.cloud. Check whether RIPE Stat ASN lookups (e.g. Akamai AS12222, AS16625, AS20940, AS21342; Imperva AS19551, AS209242) would be a safe upgrade path.
- **Apple coarse-grained** — 17.0.0.0/8 matches all of Apple, not just Applebot. Not actionable until Apple publishes a tighter list.
- **Hetzner / OVH / Azure via RIPE** — re-check periodically whether vendors have restored their own authoritative endpoints so we can drop the intermediary (RIPE delegation changes occasionally lag vendor allocations).
- **Async rDNS resolver** — `RdnsVerifier` ships in Phase 1 for Yandex/Baidu/Sogou/Seznam/Naver/Mojeek using forward-confirmed reverse DNS, but the PTR + A/AAAA lookups go through PHP's synchronous system resolver. Cold-cache hits therefore pay the resolver's default timeout (1–5s) on the request path; warm hits are O(storage-get) thanks to the 24h positive / 5min negative cache. Phase 2 should replace the synchronous calls with an async resolver or a wp-cron warmer that keeps the cache hot for recently-seen client IPs, removing the cold-cache hit from the request path entirely.
- **Linear CIDR scan is the perf bottleneck on HUMAN requests** — `IpRangeLookup::find()` iterates every CIDR of every provider until it finds a match or exhausts the list. For HUMAN classification (no UA signature match → fall through to datacenter detector → iterate ~27,000 datacenter CIDRs) this averages ~30ms per classify with the current bundled data. Slite's `<2ms warm caches` target implies a compiled-CIDR index (binary search over sorted `(network, prefix)` tuples, or a radix trie) should replace the linear scan before production rollout. The `ClassifierIntegrationTest::test_perf_smoke_baseline` test anchors the current number so future optimisation passes can measure improvement. Note: Azure alone contributes ~14,100 CIDRs; the size-reduction follow-up above would also help.
