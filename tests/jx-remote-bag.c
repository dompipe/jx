#include "../host/common/jx-remote-bag.h"
#include <assert.h>
#include <stdio.h>
#include <string.h>

static int listener_calls = 0;
static int listener_ok(const jx_bag_listener_event *event, void *ctx) {
    (void)ctx;
    assert(event->phase == JX_BAG_LISTENER_PREPARE);
    listener_calls++;
    return 0;
}
static int listener_veto(const jx_bag_listener_event *event, void *ctx) {
    (void)event; (void)ctx;
    return -1;
}
static void fill(uint8_t d[JX_BAG_PATCH_DIGEST_BYTES], uint8_t v) {
    memset(d, v, JX_BAG_PATCH_DIGEST_BYTES);
}

int main(void) {
    static const uint8_t json[] = "{\"temp\":72}";
    uint8_t json_digest[JX_BAG_PATCH_DIGEST_BYTES];
    fill(json_digest, 0x44u);

    jx_bag_patch_current current = {0};
    current.bag_name = "Weather";
    current.discipline = JX_BAG_DISCIPLINE_RECORD;
    current.revision = 7u;
    fill(current.schema_digest, 0x11u);
    fill(current.content_digest, 0x22u);

    jx_remote_bag_source source = {0};
    source.version = JX_REMOTE_BAG_VERSION;
    source.transport = JX_REMOTE_BAG_TRANSPORT_SSH;
    source.capability_mask = JX_REMOTE_BAG_CAP_WRITE;
    strcpy(source.source_id, "weather-feed.example");
    strcpy(source.bag_name, "Weather");
    source.last_sequence = 100u;
    source.enabled = 1u;

    jx_remote_bag_request req = {0};
    req.version = JX_REMOTE_BAG_VERSION;
    req.transport = JX_REMOTE_BAG_TRANSPORT_SSH;
    req.requested_capabilities = JX_REMOTE_BAG_CAP_WRITE;
    strcpy(req.source_id, "weather-feed.example");
    req.sequence = 101u;
    req.issued_at = 1000u;
    req.expires_at = 1100u;
    req.qualifier.version = JX_BAG_PATCH_VERSION;
    req.qualifier.discipline = JX_BAG_DISCIPLINE_RECORD;
    req.qualifier.expected_revision = 7u;
    req.qualifier.target_revision = 8u;
    strcpy(req.qualifier.bag_name, "Weather");
    fill(req.qualifier.current_schema_digest, 0x11u);
    fill(req.qualifier.current_content_digest, 0x22u);
    fill(req.qualifier.target_schema_digest, 0x11u);
    memcpy(req.qualifier.target_json_digest, json_digest, sizeof json_digest);
    req.qualifier.json_length = (uint32_t)(sizeof json - 1u);

    jx_bag_listener_registry listeners;
    jx_bag_listener_registry_init(&listeners);
    assert(jx_bag_listener_add(&listeners, "Weather", JX_BAG_CHANGE_ALL, listener_ok, NULL) == 0);

    assert(jx_remote_bag_authorize(&source, &req, 1050u) == JX_REMOTE_BAG_OK);
    assert(jx_remote_bag_prepare(&source, &req, 1050u, &current, json, sizeof json - 1u,
                                 json_digest, &listeners, NULL) == JX_REMOTE_BAG_OK);
    assert(listener_calls == 1);
    assert(source.last_sequence == 100u);
    jx_remote_bag_mark_committed(&source, &req);
    assert(source.last_sequence == 101u);
    assert(jx_remote_bag_authorize(&source, &req, 1050u) == JX_REMOTE_BAG_ERR_REPLAY);

    /* HTTPS is never mutation authority. */
    jx_remote_bag_request bad = req;
    bad.sequence = 102u;
    bad.transport = JX_REMOTE_BAG_TRANSPORT_HTTPS;
    assert(jx_remote_bag_authorize(&source, &bad, 1050u) == JX_REMOTE_BAG_ERR_TRANSPORT);

    jx_remote_bag_source https_source = source;
    https_source.transport = JX_REMOTE_BAG_TRANSPORT_HTTPS;
    bad = req;
    bad.sequence = 102u;
    bad.transport = JX_REMOTE_BAG_TRANSPORT_HTTPS;
    assert(jx_remote_bag_authorize(&https_source, &bad, 1050u) == JX_REMOTE_BAG_ERR_TRANSPORT);

    bad = req; bad.sequence = 102u;
    strcpy(bad.qualifier.bag_name, "Prices");
    assert(jx_remote_bag_authorize(&source, &bad, 1050u) == JX_REMOTE_BAG_ERR_SCOPE);

    bad = req; bad.sequence = 102u; bad.expires_at = 1049u;
    assert(jx_remote_bag_authorize(&source, &bad, 1050u) == JX_REMOTE_BAG_ERR_TIME);

    bad = req; bad.sequence = 102u;
    bad.qualifier.target_schema_digest[0] ^= 0xffu;
    assert(jx_remote_bag_authorize(&source, &bad, 1050u) == JX_REMOTE_BAG_ERR_CAPABILITY);

    source.capability_mask |= JX_REMOTE_BAG_CAP_SCHEMA;
    bad.requested_capabilities |= JX_REMOTE_BAG_CAP_SCHEMA;
    assert(jx_remote_bag_authorize(&source, &bad, 1050u) == JX_REMOTE_BAG_OK);

    jx_bag_listener_registry vetoes;
    jx_bag_listener_registry_init(&vetoes);
    assert(jx_bag_listener_add(&vetoes, "Weather", JX_BAG_CHANGE_ALL, listener_veto, NULL) == 0);
    assert(jx_remote_bag_prepare(&source, &req, 1050u, &current, json, sizeof json - 1u,
                                 json_digest, &vetoes, NULL) == JX_REMOTE_BAG_ERR_REPLAY);

    req.sequence = 103u;
    assert(jx_remote_bag_prepare(&source, &req, 1050u, &current, json, sizeof json - 1u,
                                 json_digest, &vetoes, NULL) == JX_REMOTE_BAG_ERR_LISTENER);

    puts("jx-remote-bag: SSH-only mutation, scope, freshness, capability and listener gates passed");
    return 0;
}
