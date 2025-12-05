# Pagination Domain, Logic, and Adapter Analysis

## Phase 1 — Abstract Domain Model
- **Core terms**: offset (zero-based start), limit/pageSize (positive window length), page (1-based request index), totalCount (known universe size, ≥0), nowCount/fetchedCount (items already obtained in a multi-call flow).
- **Ideal input invariants**: offset ≥ 0; limit > 0 (or explicit infinity/all sentinel); page ≥ 1 when used; pageSize > 0; totalCount ≥ 0 if supplied. Negative inputs should raise errors rather than silently clamp unless explicitly documented.
- **Coverage**: a request for `[offset, offset+limit)` should return all available items in that window (or be clearly truncated when running past totalCount). No duplicates and no gaps when moving forward.
- **Monotonicity**: increasing offset or page should never move backwards in the dataset ordering.
- **Boundaries**: exact page boundaries (offset divisible by limit) should start a fresh page of length limit; offsets just before a boundary should include tail of previous page then head of next on subsequent calls. Near dataset end, partial pages are valid with count `< limit` but must stay ordered.
- **Invalid inputs**: negative offset/page/pageSize or non-positive limits are invalid; ideal API rejects them. If an “all rows” sentinel is allowed (e.g., limit=0), it should be explicit and consistently documented.
- **Mapping**: converting (offset, limit) to (page, pageSize) typically uses `page = floor(offset/limit)+1`, `pageSize = limit`, with potential adjustments for already-fetched rows (`nowCount`) or tail truncation when totalCount is known.

## Phase 2 — Concrete Spec of somework/offset-page-logic
Public API:
- `Offset::logic(int $offset, int $limit, int $nowCount=0): OffsetLogicResult` – may throw `AlreadyGetNeededCountException` or `LogicException`. Inputs are **clamped to ≥0** before processing.
- `OffsetLogicResult` holds `page` (≥0) and `size` (≥0) with setters clamping negatives.
- Exceptions: `AlreadyGetNeededCountException` (extends `OffsetLogicException`) when the library believes requested rows are already obtained.

Piecewise behavior (post-clamp):
1. **All-zero sentinel**: offset=0, limit=0, nowCount=0 → page=0, size=0 (interpreted as “everything”).
2. **Limit-only**: offset=0, limit>0, nowCount=0 → page=1, size=limit.
3. **Offset-only**: offset>0, limit=0 → page=2, size=offset+nowCount (offset is always included, page hard-coded to 2).
4. **nowCount branch** (nowCount>0 and limit>0):
   - If `limit > nowCount`: recursively call with offset+=nowCount, limit-=nowCount (drops nowCount and shifts offset forward).
   - Else (`limit ≤ nowCount`): throw `AlreadyGetNeededCountException` with guidance to stop.
5. **Both offset & limit >0 with nowCount=0**:
   - If offset==limit → page=2, size=limit (exact boundary case).
   - If offset<limit → page=2, size=offset (limit ignored, page fixed at 2).
   - If offset>limit → find largest divisor `i` of offset from limit down to 1; return page=intdiv(offset,i)+1, size=i. This maximizes page size via greatest divisor search.
6. Any other combination throws `LogicException` (should be unreachable per comments).

Comparison to ideal model:
- Inputs are silently clamped to non-negative rather than rejected.
- `limit=0` is treated as a valid “return everything” sentinel only when offset=nowCount=0; otherwise offset-only path uses page=2 and size offset+nowCount (non-standard).
- Mapping deviates from usual `page=floor(offset/limit)+1`, `pageSize=limit` when offset<limit (page=2, size=offset) or offset==limit (page=2, size=limit) or offset>limit with divisor search (page size may shrink to a divisor, not equal to limit). This can change requested window size and positioning.
- Exception when nowCount≥limit assumes caller should stop; ideal model might permit zero-size request instead.

Key thresholds/branches: zero vs positive for each input; nowCount>0 & limit relations; offset compared to limit (>, <, ==); divisibility of offset by descending divisors; special offset>0 & limit=0 path.

## Phase 3 — Concrete Spec of somework/offset-page Adapter
Public API surface:
- `OffsetAdapter::__construct(SourceInterface $source)`.
- `OffsetAdapter::execute(int $offset, int $limit, int $nowCount=0): OffsetResult` – main entry point.
- Helper classes: `SourceInterface` (page, pageSize → SourceResultInterface), `SourceCallbackAdapter` (wraps callable into SourceInterface), `SourceResultInterface` (generator), `SourceResultCallbackAdapter` (wraps callable into SourceResultInterface), `OffsetResult` (aggregates generator results and exposes fetch/fetchAll/getTotalCount).

Internal flow:
- `execute` calls protected `logic`, which loops `while ($offsetResult = Offset::logic(...))` inside a try/catch for `AlreadyGetNeededCountException`.
- Each `OffsetLogicResult` triggers a source call `source->execute(page,size)`. If the returned generator is empty (`!$generator->valid()`), `logic` stops entirely.
- Each source result is wrapped in `SourceResultCallbackAdapter` to increment `nowCount` as yielded items flow. Offsets/limits are **not updated** inside the loop except via `nowCount` mutation; the `while` depends on `Offset::logic` returning a truthy result repeatedly, but in practice `Offset::logic` always returns an object, making this an infinite loop unless the source generator becomes invalid or exception is thrown.
- `OffsetResult` consumes the generator-of-SourceResultInterface, validates each yielded element type, flattens nested generators, and increments `totalCount` for every yielded item. Fetching is eager as you iterate: `fetch`/`fetchAll` advance the generator.

Adapter vs logic vs domain:
- Adapter forwards raw ints to logic; no validation beyond logic’s clamping. Negative offsets/limits are silently coerced to 0 by logic.
- Adapter ignores the OffsetLogicResult->page/size meaning beyond passing to source; it does **not** adjust offset/limit per iteration, so multi-iteration scenario effectively relies on logic’s recurrence solely via incremented `nowCount` captured by closure.
- Empty generator short-circuits all further pagination, even if more pages logically needed.
- `limit=0` path relies on logic’s page=0/size=0 sentinel; adapter will call source with (0,0) once, then stop if generator empty.

## Phase 4 — Edge Case Catalog (summary)
Legend: OK = consistent/defensible; Surprising = non-standard but predictable; Risk = likely bug/underspecified.

### Boundary cases
1. offset=0, limit=0, nowCount=0 → logic returns page=0,size=0; adapter calls source once and stops if empty. Ideal: could mean “no limit” or invalid; here treated as sentinel. (Surprising; logic test coverage.)
2. offset=0, limit>0, nowCount=0 → page=1,size=limit. Ideal aligns. (OK; covered in logic/adapter tests.)
3. offset>0, limit=0 → page=2,size=offset+nowCount; adapter will request that many rows starting page 2. Ideal would expect error or “all rows from offset”; current behavior changes page index arbitrarily. (Surprising/Risk; logic tests cover.)
4. offset<limit (e.g., offset=3, limit=5, nowCount=0) → logic returns page=2,size=3 (limit ignored). Adapter fetches 3 items from page 2; ideal mapping would request page1/size5 and slice offset locally. Potential gap/duplication risk. (Risk; adapter tests expect 5 items currently, masking mismatch.)
5. offset==limit (e.g., 5,5) → page=2,size=5; ideal would be page=2,size=5 aligned to boundary; acceptable. (OK; logic covered.)
6. offset>limit non-divisible (e.g., offset=47,limit=22) → divisor search yields size=1,page=48; adapter requests 1 item at page 48. Ideal would keep pageSize=22 and compute page=3. Produces drastically different window. (Risk; logic test covers.)
7. offset>limit divisible (44,22) → page=3,size=22; aligns with ideal floor division +1. (OK; covered.)
8. nowCount>0 & limit>nowCount (offset=0,limit=22,nowCount=10) → recursive call returns offset=10,limit=12 → path offset>limit? no offset<limit? yes? Actually offset=10, limit=12 leads page=2,size=10. Adapter calls page2 size10 once; fetched count increments to 10, loop repeats with same offset/limit unless source empties. Ideal might continue to fetch remaining 2 items; here remaining 2 are never explicitly requested. (Risk; partially covered in logic tests but adapter sequence not.)
9. nowCount>=limit (offset=0,limit=5,nowCount=5) → exception; adapter catches and stops without yielding. Ideal could return zero items gracefully. (Surprising but documented via tests.)
10. Negative inputs (offset/limit/nowCount <0) → clamped to 0; may trigger sentinel branches. Ideal: reject. (Surprising; logic tests include.)

### Adapter interaction/loop cases
11. `Offset::logic` always truthy → adapter’s `while` relies on source generator becoming invalid to break; if source always yields at least one item, loop is infinite (generator-of-generators unbounded). No offset/limit decrement occurs. Ideal: should stop after fulfilling limit. (Risk; adapter tests inadvertently depend on empty generator to stop.)
12. Empty first page from source (generator invalid) → adapter stops without requesting further pages even if offset>0. Ideal might try next page or return empty slice explicitly. (Surprising.)
13. `limit=0, offset=0` with source yielding items despite size=0 → adapter will yield them all once; totalCount counts them. Ideal sentinel may expect no call or zero results. (Surprising; adapter test `testError` shows one item returned.)
14. Source returns non-SourceResult objects in generator → `OffsetResult` throws UnexpectedValueException. (OK; defensive.)

### Sequence/gap/duplication risks
15. Sequential calls for offset ranges relying on logic divisor path (e.g., offset=3 limit=5 then offset=8 limit=5) may overlap or gap because page sizes vary with offset instead of fixed limit slicing. (Risk; no tests.)
16. nowCount progression within a single adapter call depends on source yielding items; if source yields fewer than logic’s requested size, `nowCount` increments less and loop repeats same parameters, potentially re-fetching same page endlessly. (Risk; untested.)

Test coverage: logic tests cover most individual branches; adapter tests are broad but assert current behavior rather than domain-ideal and miss multi-iteration/loop behaviors and nowCount recursion effects.

## Phase 5 — Gaps, Inconsistencies, Risks
1. **Offset<limit mapping shrink** (domain vs logic): limit ignored, page forced to 2; adapter returns fewer items than requested and shifted window. Recommend documenting or adjusting mapping; add adapter tests to capture actual vs desired behavior.
2. **Divisor search for offset>limit** (logic): pageSize may drop to small divisor (even 1) causing many tiny page fetches; deviates from standard pagination and can explode request count. Needs doc/validation; adapter should be tested for this branch.
3. **nowCount recursion loses remaining items**: logic reduces limit by nowCount then adapter never updates offset/limit per iteration, so only one recursive result is used; remaining portion of requested limit is skipped. Add adapter tests and consider iterating Offset::logic again with updated params.
4. **Potential infinite loop**: Offset::logic always returns object; adapter loop terminates only when source generator is invalid. A source that always yields at least one item will cause unbounded pagination. Needs guard (e.g., break when size==0 or after one iteration) and tests.
5. **Sentinel/zero handling**: limit=0/off=0 treated as “all rows” but adapter still calls source with size=0; behavior if source returns data is ambiguous. Should clarify docs and test explicitly.
6. **Negative input clamping**: silently converted to 0, possibly triggering sentinel paths. Ideally validate and throw; at minimum document and test the clamping.

## Phase 6 — Test Plan for Adapter
Goals: capture domain-ideal expectations vs actual logic behavior; ensure integration with somework/offset-page-logic branches.

### Test Matrix (adapter-level)
| Scenario | Inputs (offset,limit,nowCount) | Logic result passed to source | Expected outcome (actual) | Domain ideal? | Notes/tests |
| --- | --- | --- | --- | --- | --- |
| Zero sentinel | (0,0,0) | page=0,size=0 | Calls source once; returns whatever source yields; stops if empty | Ideal ambiguous | Add `testZeroLimitOffset` |
| Limit-only | (0,5,0) | page=1,size=5 | 5 items from first page | Aligns | Existing basic test |
| Offset-only | (5,0,0) | page=2,size=5 | Requests 5 rows from page 2 | Ideal would error/all rows from offset | New test `testOffsetOnlyLimitZero` |
| Offset<limit | (3,5,0) | page=2,size=3 | Returns 3 items (current logic) | Ideal would return 5 starting at 4th item | New test asserting current vs documenting delta |
| Offset>limit divisible | (44,22,0) | page=3,size=22 | Returns slice starting at 45th item | Aligns | Add integration check |
| Offset>limit non-divisible | (47,22,0) | page=48,size=1 | Single item request; potential gap | Ideal would request size=22 page=3 | New test `testOffsetGreaterLimitNonDivisible` |
| nowCount recursion | (0,22,10) | page=2,size=10 | Adapter fetches 10 items then stops | Ideal would fetch remaining 12 | New test capturing skipped remainder |
| nowCount exception | (0,5,5) | exception -> adapter stops | No data returned | Accept with doc | Adapter test for exception swallow |
| Empty first generator | source returns empty for requested page | loop ends | Empty result even if more pages exist | Surprising | New test |
| Non-terminating source | source always valid with infinite data | Potential infinite loop | Should guard | Stress test expecting break after one iteration or size==0 |
| Negative inputs | (-3,5,-2) | clamped to (0,5,0) → page=1,size=5 | Returns first 5 items | Ideal reject | Test to document clamping |
| limit=0 with data | (0,0,0) but source yields data despite size=0 | Adapter returns data once | Ideal unclear | Test confirms behavior |

### Example PHPUnit Test Skeletons
- **testOffsetOnlyLimitZero**: Given offset=5, limit=0, source returns sequence per page/size; expect adapter to request page=2,size=5 and yield that page only; assert totalCount=5; note deviation from ideal.
- **testOffsetLessThanLimitShrinksRequest**: offset=3, limit=5 with deterministic source; assert page=2,size=3 invocation and only 3 items returned; document mismatch vs ideal 5-item window.
- **testOffsetGreaterLimitNonDivisible**: offset=47, limit=22; source spies on received arguments; expect single call page=48,size=1, totalCount=1.
- **testNowCountRecursivePartialFetch**: source yields exactly `size` items; call execute(0,22,10); assert only 10 items returned and no subsequent source calls; highlight skipped remainder.
- **testSourceNeverInvalidInfiniteLoopGuard**: use source that always yields one item with valid generator; add timeout/iteration counter to ensure adapter stops after one iteration or throws; currently would hang—test marks as expected failure to signal risk.
- **testNegativeInputsClamped**: execute(-3,5,-2) and assert behaves same as (0,5,0); ensures clamping documented.
- **testEmptyGeneratorStopsPagination**: source returns empty generator for first page; ensure adapter result empty and no further calls.

Each test should clarify whether it asserts current behavior (contract with logic) or ideal expectation; surprising behaviors can be annotated with `@group behavior` vs `@group ideal` to distinguish.
