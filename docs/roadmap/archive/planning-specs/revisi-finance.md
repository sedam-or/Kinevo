> ARCHIVED 2026-08-31 (R0): owner pricing revision patch (2026-08-28). Migrated into
> docs/billing.md (Pricing Delta), ADR-013 (superseding-price note), the D-001..D-008 records
> (docs/roadmap/archive/task-legacy/commercial-pricing-delta-post-p27.md), and
> docs/ai-economics/. Historical evidence — NOT current policy by itself.

# KINEVO — COMMERCIAL & AI ECONOMICS DELTA PATCH
# SCOPE: ONLY PRICING / ENTITLEMENT / AI ECONOMICS CHANGES
# PURPOSE: Replace the previous Free/Pro/Power commercial assumptions.
#
# IMPORTANT:
# This prompt is a PATCH to the existing P28–P39 roadmap.
# DO NOT regenerate, reorder, or reinterpret the entire P28–P39 roadmap.
# Modify ONLY the areas explicitly listed below.
#
# =====================================================================
# 0. AUTHORITATIVE BUSINESS DECISION
# =====================================================================
#
# Replace the previous monthly pricing:
#
# OLD:
#   Free  = IDR 0
#   Pro   = IDR 34,900
#   Power = IDR 49,900
#
# WITH:
#
# NEW:
#   Free  = IDR 0 / month
#   Pro   = IDR 49,900 / month
#   Power = IDR 89,900 / month
#
# These are:
#
#   LAUNCH PRICE HYPOTHESES
#
# They are NOT declared permanent market truth.
#
# The beta/release process MUST measure:
#
#   activation
#   conversion
#   retention
#   cancellation
#   willingness-to-pay
#   AI COGS
#   contribution margin
#
# before treating pricing as mature.
#
# =====================================================================
# 1. PROPOSITION OF EACH TIER
# =====================================================================
#
# Do NOT position tiers as arbitrary feature unlocks.
#
# FREE:
#
#   "Discover Kinevo."
#
# Purpose:
#   allow the user to experience the complete core product loop.
#
# PRO — IDR 49,900/month:
#
#   "Make Kinevo part of your workflow."
#
# Purpose:
#   serious individual users who rely on Kinevo regularly.
#
# POWER — IDR 89,900/month:
#
#   "Make Kinevo deeply personal."
#
# Purpose:
#   high-intensity personal users who use Kinevo as a central operating system.
#
# ---------------------------------------------------------------------
# POWER POSITIONING RULE
# ---------------------------------------------------------------------
#
# Power MUST NOT be implemented as:
#
#   "Pro + random features"
#
# Power MUST be differentiated primarily by:
#
#   higher usage capacity
#   deeper historical analysis
#   deeper product intelligence
#   richer reflection
#   advanced Wrapped/share capabilities
#   greater personal workspace capacity
#   stronger AI allowance
#   additional convenience where justified
#
# Power MUST NOT add:
#
#   Teams
#   Organizations
#   RBAC
#   Enterprise
#
# =====================================================================
# 2. EXISTING ENTITLEMENT MATRIX — MODIFY, DO NOT DUPLICATE
# =====================================================================
#
# Reuse the existing entitlement service/catalog.
#
# Do NOT create a second plan/entitlement system.
#
# Update the existing plan records/configuration to conceptually represent:
#
# ---------------------------------------------------------------------
# FREE
# ---------------------------------------------------------------------
#
#   price_monthly                = 0
#   workspace.max                = 1
#   ai.hosted                    = limited
#   ai.byok                      = false
#   analytics.basic              = true
#   analytics.advanced           = false
#   insights.basic               = true
#   insights.advanced            = false
#   wrapped.basic                = true
#   wrapped.yearly               = basic
#   wrapped.advanced_share       = false
#   history.depth                = baseline
#   mobile.access                = true
#
# ---------------------------------------------------------------------
# PRO
# ---------------------------------------------------------------------
#
#   price_monthly                = 49,900
#   workspace.max                = 5
#   ai.hosted                    = higher allowance
#   ai.byok                      = true
#   analytics.basic              = true
#   analytics.advanced           = true
#   insights.basic               = true
#   insights.advanced            = true
#   wrapped.basic                = true
#   wrapped.yearly               = true
#   wrapped.advanced_share       = true
#   history.depth                = extended
#   mobile.access                = true
#
# ---------------------------------------------------------------------
# POWER
# ---------------------------------------------------------------------
#
#   price_monthly                = 89,900
#   workspace.max                = 15
#   ai.hosted                    = highest launch allowance
#   ai.byok                      = true
#   analytics.basic              = true
#   analytics.advanced           = true
#   insights.basic               = true
#   insights.advanced            = true
#   wrapped.basic                = true
#   wrapped.yearly               = true
#   wrapped.advanced_share       = expanded
#   history.depth                = deep
#   mobile.access                = true
#
# IMPORTANT:
#
# The exact AI allowance MUST NOT be hardcoded as a business assumption
# before the FinOps simulation described below.
#
# =====================================================================
# 3. REMOVE THE OLD CREDIT NUMBERS FROM BUSINESS TRUTH
# =====================================================================
#
# The previous:
#
#   Free  = 20 credits
#   Pro   = 150 credits
#   Power = 500 credits
#
# MUST NOT remain classified as final/locked pricing policy.
#
# Reclassify these as:
#
#   DEPRECATED BASELINE
#
# or remove them if they are not referenced by implementation.
#
# Do NOT simply replace them with arbitrary new numbers.
#
# The new AI allowance MUST be derived from:
#
#   target AI COGS
#   user usage distribution
#   model/provider pricing
#   cache behavior
#   P50/P95/P99 usage
#   desired contribution margin
#
# =====================================================================
# 4. AI CREDIT PRINCIPLE
# =====================================================================
#
# Preserve:
#
#   Kinevo AI Credit != token
#   Kinevo AI Credit != provider billing unit
#
# AI tokens are telemetry.
#
# AI credits are an internal product/economic abstraction.
#
# Provider cost is the underlying measurable economic value.
#
# ---------------------------------------------------------------------
# REQUIRED ECONOMIC FLOW
# ---------------------------------------------------------------------
#
#   Provider usage
#       ↓
#   Actual/estimated provider cost
#       ↓
#   Kinevo normalized economic value
#       ↓
#   Kinevo credit consumption
#
# DO NOT use:
#
#   1 credit = X tokens
#
# as the primary pricing rule.
#
# =====================================================================
# 5. INTRODUCE / PRESERVE TWO AI LEDGERS
# =====================================================================
#
# The system MUST conceptually separate:
#
# ---------------------------------------------------------------------
# SUBSCRIPTION LEDGER
# ---------------------------------------------------------------------
#
#   subscription plan
#   billing period
#   amount
#   payment state
#
# ---------------------------------------------------------------------
# AI USAGE LEDGER
# ---------------------------------------------------------------------
#
#   included AI allowance
#   consumed AI credits
#   optional prepaid AI balance, if implemented
#   hosted usage
#   BYOK usage
#   provider/model
#   token telemetry
#   estimated provider cost
#
# Never merge these into one undifferentiated balance.
#
# =====================================================================
# 6. HOSTED AI VS BYOK — LOCKED
# =====================================================================
#
# Kinevo-managed/hosted AI:
#
#   Kinevo pays provider.
#   User consumes included Kinevo AI allowance.
#
# BYOK:
#
#   User/provider relationship owns inference expense.
#   Kinevo-hosted AI credits MUST NOT be consumed.
#
# BYOK remains subject to:
#
#   rate limit
#   request limit
#   context limit
#   maximum output
#   timeout
#   abuse protection
#
# BYOK is available:
#
#   Free  = NO
#   Pro   = YES
#   Power = YES
#
# =====================================================================
# 7. AI COST SIMULATION — REQUIRED BEFORE FINAL CREDIT QUOTA
# =====================================================================
#
# Create/extend an AI Cost Simulator.
#
# This is REQUIRED before locking final included AI budgets.
#
# Inputs:
#
#   provider
#   model
#   feature
#   request count
#   input tokens
#   cached input tokens
#   output tokens
#   cache-hit ratio
#   user tier
#   billing period
#
# Scenarios:
#
#   P50
#   P75
#   P90
#   P95
#   P99
#   abuse/heavy-user
#
# Output:
#
#   provider COGS
#   normalized Kinevo cost
#   included budget exposure
#   expected overage
#   contribution margin
#
# ---------------------------------------------------------------------
# REQUIRED FEATURES FOR COST PROFILE
# ---------------------------------------------------------------------
#
# At minimum:
#
#   Goal Breakdown
#   Note Summary
#   Task Extraction
#   Weekly/Daily Planning
#   Deep Analysis
#   Wrapped Narrative
#
# Every feature MUST have a usage profile rather than one invented average.
#
# =====================================================================
# 8. TARGET MARGIN POLICY
# =====================================================================
#
# The system MUST NOT hardcode:
#
#   "AI markup = 25%"
#
# as a permanent commercial policy.
#
# Instead:
#
#   provider cost
#       ↓
#   desired contribution margin
#       ↓
#   customer AI charge
#
# Remember:
#
#   25% markup on $1 cost = $1.25 revenue
#   gross margin = 20%
#
# Therefore markup and margin MUST NOT be conflated.
#
# Recommended management target:
#
#   AI contribution margin target = approximately 30–50%
#
# This is a management target, NOT a hard technical invariant.
#
# Actual target MUST remain configurable.
#
# =====================================================================
# 9. OPTIONAL AI TOP-UP / PREPAID BALANCE
# =====================================================================
#
# The pricing architecture SHOULD support:
#
#   included AI allowance
#       +
#   optional AI top-up
#
# without making top-up mandatory for normal use.
#
# Example UX:
#
#   "Your included Kinevo AI allowance is almost used."
#
#   [Add AI Balance]
#
# Minimum top-up amount MUST NOT be invented in this patch.
#
# Before enabling top-up:
#
# calculate:
#
#   payment fee
#   fixed gateway fee
#   percentage gateway fee
#   effective take rate
#   fraud/refund risk
#
# Very small top-ups MUST be rejected if payment economics make them
# inefficient.
#
# =====================================================================
# 10. AI REQUEST BUDGET FIREWALL
# =====================================================================
#
# BEFORE provider request:
#
#   authentication
#   ↓
#   entitlement
#   ↓
#   available AI allowance
#   ↓
#   rate limit
#   ↓
#   estimated request budget
#   ↓
#   max input/output token guard
#   ↓
#   provider request
#
# If budget is insufficient:
#
#   DO NOT call provider.
#
# ---------------------------------------------------------------------
# RESERVE → SETTLE
# ---------------------------------------------------------------------
#
# Before provider call:
#
#   reserve maximum permitted request budget.
#
# After provider response:
#
#   measure actual usage.
#   calculate actual/estimated provider cost.
#   settle actual consumption.
#   release unused reservation.
#
# Do not permanently charge maximum output if actual usage is lower.
#
# =====================================================================
# 11. TOKEN METERING
# =====================================================================
#
# Store, where the provider exposes these values:
#
#   input_tokens
#   cached_input_tokens
#   output_tokens
#   total_tokens
#
# Also store:
#
#   provider
#   model
#   pricing_version
#   estimated_provider_cost
#   billing_source
#   status
#   latency
#   request_id
#
# ---------------------------------------------------------------------
# BILLING SOURCE ENUMERATION
# ---------------------------------------------------------------------
#
# At minimum conceptually:
#
#   INCLUDED_HOSTED
#   PREPAID_HOSTED
#   BYOK
#
# Do NOT charge:
#
#   BYOK
#
# against:
#
#   INCLUDED_HOSTED
#   PREPAID_HOSTED
#
# =====================================================================
# 12. PROVIDER PRICE CATALOG
# =====================================================================
#
# Never hardcode model prices in business logic.
#
# Required conceptual fields:
#
#   provider
#   model
#   currency
#   input_rate
#   cached_input_rate
#   output_rate
#   effective_from
#   effective_until
#   pricing_version
#   source
#
# If provider pricing changes:
#
#   create a new price version.
#
# Historical AI usage MUST remain reproducible.
#
# =====================================================================
# 13. PRICING PAGE UPDATE
# =====================================================================
#
# Change all user-facing prices:
#
#   Pro   = Rp49.900/month
#   Power = Rp89.900/month
#
# Do NOT leave old prices in:
#
#   frontend constants
#   copy
#   tests
#   fixtures
#   screenshots
#   marketing text
#   seeded plans
#   documentation
#   JSON config
#
# Search repository for:
#
#   34.900
#   49.900
#
# and classify every occurrence:
#
#   replace
#   historical documentation
#   test fixture
#   migration history
#   intentionally preserved
#
# Do not blindly global-replace migration history.
#
# =====================================================================
# 14. PRICING UX — DO NOT MAKE POWER LOOK FAKE
# =====================================================================
#
# Pricing cards MUST communicate:
#
# FREE:
#   "Experience the system."
#
# PRO:
#   "For serious personal use."
#
# POWER:
#   "For intensive personal use."
#
# The user must be able to answer:
#
#   What do I get?
#   Why would I upgrade?
#   Why is Power worth the extra Rp40.000?
#
# ---------------------------------------------------------------------
# PRICE DIFFERENCE
# ---------------------------------------------------------------------
#
# Pro:
#
#   Rp49.900
#
# Power:
#
#   Rp89.900
#
# Difference:
#
#   Rp40.000/month
#
# Power value explanation MUST explicitly focus on:
#
#   capacity
#   depth
#   intelligence
#   history
#   usage allowance
#   advanced reflection
#
# NOT:
#
#   arbitrary cosmetic features.
#
# =====================================================================
# 15. PAYWALL / UPGRADE UX
# =====================================================================
#
# When user reaches an entitlement limit:
#
# Show:
#
#   what limit was reached
#   why it exists
#   what Pro/Power changes
#   what the user can do now
#
# Avoid:
#
#   deceptive urgency
#   fake scarcity
#   manipulative countdowns
#   destructive lockout of existing data
#
# ---------------------------------------------------------------------
# EXAMPLE
# ---------------------------------------------------------------------
#
# "You've used your included Kinevo AI allowance for this billing period."
#
# "Upgrade to Pro for a larger AI allowance and BYOK support."
#
# or:
#
# "Use your own provider with BYOK."
#
# =====================================================================
# 16. DOWNGRADE RULE
# =====================================================================
#
# If:
#
#   Power → Pro
#   Pro → Free
#
# Existing data MUST NOT be silently deleted.
#
# Instead:
#
#   creation/edit limits apply
#   advanced capabilities become unavailable
#   history access follows entitlement
#   existing data remains preserved
#
# If current state exceeds a new limit:
#
#   read access where safe
#   prevent new usage beyond entitlement
#
# Do NOT destroy user data simply to satisfy a lower limit.
#
# =====================================================================
# 17. BETA PRICING VALIDATION
# =====================================================================
#
# Treat:
#
#   Rp49.900 Pro
#   Rp89.900 Power
#
# as launch hypotheses.
#
# Measure:
#
#   pricing page view
#   upgrade CTA click
#   checkout start
#   checkout completion
#   first paid action
#   D7 retention
#   D30 retention
#   cancellation
#   downgrade
#   Power selection rate
#   AI COGS per paid user
#
# ---------------------------------------------------------------------
# POWER-SPECIFIC METRIC
# ---------------------------------------------------------------------
#
# Track:
#
#   Pro → Power upgrade rate
#
# and qualitative answer:
#
#   "Why did you choose Power?"
#
# If Power conversion is low:
#
#   do NOT immediately add random features.
#
# First classify:
#
#   insufficient perceived value
#   insufficient usage need
#   poor communication
#   price resistance
#   weak product depth
#
# =====================================================================
# 18. UNIT ECONOMICS MODEL
# =====================================================================
#
# For every plan calculate:
#
#   subscription revenue
#   AI revenue, if any
#   payment fees
#   hosted AI COGS
#   infrastructure COGS
#   storage/bandwidth
#   directly attributable support
#
# Then:
#
#   gross contribution
#   contribution margin
#
# ---------------------------------------------------------------------
# REQUIRED SCENARIOS
# ---------------------------------------------------------------------
#
# FREE:
#   expected
#   heavy
#   abuse
#
# PRO:
#   P50
#   P95
#   P99
#
# POWER:
#   P50
#   P95
#   P99
#
# Do not validate only average users.
#
# =====================================================================
# 19. OPENAI / DEEPSEEK / OTHER PROVIDERS
# =====================================================================
#
# Provider-specific prices MUST be verified against current official sources
# before creating/refreshing a pricing catalog.
#
# Do not assume:
#
#   OpenCode Go subscription price
#   model access
#   reseller rights
#   commercial usage rights
#   rate limits
#   concurrency
#
# are equivalent to raw API economics.
#
# Development provider:
#
#   may use OpenCode Go / compatible development gateway if configured.
#
# Production provider:
#
#   MUST use a provider/gateway arrangement whose commercial terms permit the
#   intended Kinevo SaaS use.
#
# =====================================================================
# 20. MIDTRANS ECONOMICS
# =====================================================================
#
# The existing Midtrans Sandbox implementation remains valid development
# evidence only.
#
# The new pricing model MUST NOT assume:
#
#   one flat payment fee per user.
#
# Payment cost must be modeled by:
#
#   payment method
#   fixed fee
#   percentage fee
#   subscription transaction amount
#   AI top-up transaction amount
#
# Production merchant configuration is separate from Sandbox.
#
# =====================================================================
# 21. REQUIRED REGRESSION SEARCH
# =====================================================================
#
# Search the full repository for old commercial assumptions:
#
#   34.900
#   49.900
#   20 credits
#   150 credits
#   500 credits
#   25% markup
#
# Classify each occurrence.
#
# Correct:
#
#   production config
#   plan seeders
#   tests
#   frontend pricing
#   documentation
#   API examples
#   fixtures
#
# Preserve historical:
#
#   migration history
#   changelog history
#
# when modification would corrupt history.
#
# =====================================================================
# 22. REQUIRED TESTS
# =====================================================================
#
# Add/update tests for:
#
# ---------------------------------------------------------------------
# PLAN
# ---------------------------------------------------------------------
#
# [ ] Free price = 0
# [ ] Pro price = 49,900
# [ ] Power price = 89,900
#
# ---------------------------------------------------------------------
# ENTITLEMENT
# ---------------------------------------------------------------------
#
# [ ] Free limits
# [ ] Pro limits
# [ ] Power limits
# [ ] BYOK Free rejected
# [ ] BYOK Pro accepted
# [ ] BYOK Power accepted
#
# ---------------------------------------------------------------------
# AI LEDGER
# ---------------------------------------------------------------------
#
# [ ] hosted consumes allowance
# [ ] BYOK does not consume hosted allowance
# [ ] insufficient allowance stops provider call
# [ ] reservation releases correctly
# [ ] actual usage settles correctly
#
# ---------------------------------------------------------------------
# DOWNGRADE
# ---------------------------------------------------------------------
#
# [ ] Power → Pro
# [ ] Pro → Free
# [ ] existing data preserved
#
# ---------------------------------------------------------------------
# PRICING UX
# ---------------------------------------------------------------------
#
# [ ] current prices rendered
# [ ] old prices absent from active UI
# [ ] upgrade CTA correct
#
# =====================================================================
# 23. REQUIRED TASK BOARD CHANGE
# =====================================================================
#
# Do NOT create duplicate P28–P39 phases.
#
# Add a clearly marked DELTA section to TASK.md:
#
# ## Commercial Pricing Delta — Post-P27
#
# Record:
#
#   new prices
#   why pricing changed
#   launch-hypothesis status
#   old-price replacement evidence
#   AI economics dependency
#   FinOps simulation status
#
# Example status:
#
#   PRICING DECISION = LOCKED
#   AI QUOTA NUMBERS = NOT YET LOCKED
#
# =====================================================================
# 24. REQUIRED DOCUMENTATION CHANGES
# =====================================================================
#
# Update existing authoritative documents only:
#
#   docs/pricing.md
#   docs/saas.md
#   docs/billing.md
#   docs/ai-architecture.md
#   docs/architecture.md
#   docs/implementation-status.md
#   TASK.md
#
# Pricing documentation MUST explicitly say:
#
#   Pro = Rp49.900/month
#   Power = Rp89.900/month
#
# and:
#
#   launch pricing is subject to beta validation.
#
# Do NOT claim:
#
#   "final market price"
#
# =====================================================================
# 25. DEFINITION OF DONE
# =====================================================================
#
# This pricing delta is DONE only when:
#
# [ ] old Pro price removed from active commercial configuration
# [ ] old Power price removed from active commercial configuration
# [ ] Pro = Rp49.900
# [ ] Power = Rp89.900
# [ ] pricing page updated
# [ ] upgrade UX updated
# [ ] plan tests updated
# [ ] entitlement tests updated
# [ ] documentation updated
# [ ] TASK.md updated
# [ ] AI credit numbers NOT falsely marked final
# [ ] AI cost simulator exists or an existing simulator is extended
# [ ] P50/P95/P99 simulation evidence exists
# [ ] provider price catalog is versioned
# [ ] hosted vs BYOK ledger behavior tested
# [ ] preflight AI budget gate tested
# [ ] Midtrans sandbox regression passes
# [ ] no production payment assumption is inferred from sandbox
# [ ] no raw provider secret appears client-side
#
# =====================================================================
# 26. AGENT EXECUTION PROTOCOL
# =====================================================================
#
# Before modifying:
#
#   inspect current implementation.
#
# After modifying:
#
#   search old values.
#
# Then:
#
#   run relevant tests.
#
# If the repository already satisfies a requirement:
#
#   verify it.
#
# If a required value cannot be derived:
#
#   mark DECISION_REQUIRED.
#
# Do not invent:
#
#   AI quota
#   top-up price
#   annual price
#   trial
#   discount
#   provider commercial rights
#
# The only pricing numbers currently locked by this patch are:
#
#   Pro   = Rp49.900/month
#   Power = Rp89.900/month
#
# =====================================================================
# END — KINEVO COMMERCIAL & AI ECONOMICS DELTA PATCH
# =====================================================================