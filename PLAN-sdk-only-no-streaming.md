# Refactor: SDK-only routing, strip streaming

**Branch:** `refactor/sdk-only-no-streaming`
**Status:** planned, not started
**Motivation:** The codebase has 4+ parallel implementations of "send a chat completion to a provider" — one bespoke direct path per vendor (OpenAI, Anthropic, Google, OpenAI-compatible) plus the WP AI Client SDK fallback. Bugs only get fixed in one path at a time (see `fix/sdk-provider-reply-flush` for the most recent). The whole point of the SDK is to abstract this — use it for everything.

## Decision: drop streaming for now

`WordPress\AiClient\Providers\Models\TextGeneration\Contracts\TextGenerationModelInterface` only declares `generateTextResult()`. There is no `streamGenerateTextResult` method. The interface comment mentions streaming but it isn't implemented in the bundled php-ai-client (`wp-includes/php-ai-client`).

We accept the UX regression (replies appear in one chunk after the loop completes, not token-by-token) in exchange for a single, correct, maintainable code path. When the SDK adds streaming we re-introduce SSE without re-introducing per-vendor branches.

## What gets deleted

### `includes/Core/AgentLoop.php`
- `send_prompt_openai()` — entire method
- `send_prompt_anthropic()` — entire method
- `send_prompt_google()` — entire method
- `send_prompt_direct()` — entire method
- `send_prompt_direct_streaming()` — entire method
- `build_openai_tools()` — only used by the deleted methods
- `build_openai_messages()` — only used by the deleted methods
- The `'openai'` / `'anthropic'` / `'google'` / `'ai-provider-for-any-openai-compatible'` early-route branches in `send_prompt()`
- `private ?SseStreamer $sse_streamer` field, constructor wiring, and every `$this->sse_streamer->send_*()` call inside `run()`
- `reply_was_streamed()` method + `$reply_streamed` flag (added in `fix/sdk-provider-reply-flush` as a temporary bridge)
- The followup-on-empty-reply hack at line ~351 may also become unnecessary once we trust the SDK consistently

### `includes/REST/RestController.php`
- Convert `handle_stream` → `handle_chat`: drop `SseStreamer`, remove `start()` / token / tool / done event emission, return a regular `WP_REST_Response` JSON object containing `{ reply, history, tool_calls, token_usage, iterations_used, model_id, cost_estimate, generated_title?, exit_reason? }`
- Drop `call_openai_for_title` / `call_anthropic_for_title` / `call_google_for_title` / `call_openai_compat_for_title` — replace `generate_session_title` body with one SDK call
- The route registration: drop `text/event-stream` handling, register as a normal `CREATABLE` route returning JSON

### Whole files to delete
- `includes/REST/SseStreamer.php`
- `includes/Core/SimpleAiResult.php` (was only returned by the deleted direct paths)

### `includes/Core/CredentialResolver.php`
- `getOpenAiCompatEndpointUrl()` / `getOpenAiCompatApiKey()` / `getOpenAiCompatTimeout()` / `isOpenAiCompatConfigured()` and the corresponding option constants — only consumed by `send_prompt_direct()`
- Verify nothing else (settings UI, onboarding, tests) reads `OPENAI_COMPAT_ENDPOINT_OPTION` before deleting

### `includes/Core/Settings.php`
- `Settings::DIRECT_PROVIDERS` constant + any per-provider key getters that exist only to feed the deleted direct paths
- Defaults flow through the SDK provider plugins (`ai-provider-for-openai`, etc.) instead

### `includes/Benchmark/BenchmarkRunner.php`
- Update / remove `SimpleAiResult` references

### Frontend `src/store/slices/sessionsSlice.js`
- Replace the SSE parser at lines ~870-1090 with `await fetch(...).then(r => r.json())`
- Drop `appendStreamingText`, `setIsStreaming`, `setStreamingText`, `setStreamAbortController`
- Drop the streaming-text rendering branch in `<MessageList>`
- Drop the abort-controller / 120s-timeout SSE-specific UX
- Loading state: simple `sending` flag + spinner; no token accumulator

### Tests
- `src/components/__tests__/ChatPanel.test.js` — update mocks for non-streaming response
- Any PHPUnit tests asserting `SimpleAiResult` shape

## What stays

- `AgentLoop::run()` — the actual loop logic, tool dispatch, history trimming, spin detection, tool result truncation, confirmation pause, attachments. None of this is streaming-specific.
- `ConversationTrimmer`, `ToolResultTruncator`, `ContextProviders`, `AbilityResolver`, etc.
- The `confirmation_required` flow — frontend already handles it via the JSON response (the SSE event was just a wrapper around the same payload)
- Session persistence in `Database`
- Cost calculation, token usage tracking

## Migration risks

1. **Anthropic stops working** until `wp-content/plugins/ai-provider-for-anthropic-max` is fixed. The current `debug.log` shows it fatals on a missing `getJsonSchema()` method:
   ```
   PHP Fatal error: Class AnthropicMaxAiProvider\Authentication\AnthropicOAuthRequestAuthentication
   contains 1 abstract method and must therefore be declared abstract or implement
   the remaining method (WordPress\AiClient\Common\Contracts\WithJsonSchemaInterface::getJsonSchema)
   ```
   **Block this refactor on a 1-line fix to that plugin first.** Add the missing `getJsonSchema()` implementation and verify the provider registers.

2. **OpenAI/Google API keys** stored under our settings need to migrate to whatever the SDK provider plugins read. Audit before deleting `Settings::DIRECT_PROVIDERS`.

3. **Loss of token-by-token UX.** Mitigation: a "Thinking…" placeholder + spinner. For a tool-heavy 30-second loop the user sees no intermediate progress, but they at least see *something*. Document this in the PR.

4. **`tool_call` / `tool_result` events** were emitted via SSE for the debug panel. After the strip they only appear in the final JSON `tool_calls` array. The debug panel needs to render them post-hoc instead of live. Check `src/components/debug-panel.js`.

5. **Generated session titles** currently use a separate non-streaming call. After the strip this becomes a `wp_ai_client_prompt()->generate_text_result()` call with the same model, no per-vendor branches needed.

## Order of work

1. Fix the Anthropic Max provider plugin fatal (separate PR, ~5 lines)
2. Audit `Settings::DIRECT_PROVIDERS` consumers and migrate key storage if needed
3. Backend strip:
   - Delete the four direct-path methods + their helpers
   - Simplify `send_prompt()` to just call the SDK builder
   - Convert `handle_stream` → `handle_chat` returning JSON
   - Delete `SseStreamer.php`, `SimpleAiResult.php`
   - Run PHPUnit
4. Frontend rewrite:
   - Replace SSE parser in `sessionsSlice.js`
   - Update `<ChatPanel>` / `<MessageList>` / `<DebugPanel>` for the new shape
   - Run frontend tests
5. Manual smoke test: chat with each provider (OpenAI, Anthropic, Google, the connector plugin), verify tool calls work, verify confirmation pause works
6. Update `CHANGELOG.md` documenting the streaming regression

## Estimated diff

~1,800 lines deleted, ~250 lines added. Eight files touched. One PR — this should be reviewed as a whole, not split, since the backend and frontend changes are coupled.

## Open questions

- Do we want to gate the strip behind a feature flag (`gratis_ai_agent_use_sdk_only`) for one release before deleting the old paths? Probably not — the bug surface is too wide.
- Is the debug panel's live tool-call rendering critical, or can it wait for SDK streaming?
- Any third-party integrations / addons calling `SimpleAiResult` or `send_prompt_*` methods directly? Grep before deleting.
