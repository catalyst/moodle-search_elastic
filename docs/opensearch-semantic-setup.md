# Semantic search on AWS OpenSearch: manual demo (Titan v2)

A hand-run proof of the setup that `search_elastic` will automate. Run each step in order and confirm the
**Expected** result before moving on. Nothing here touches the real Moodle indexes.

- Target: AWS OpenSearch **managed domain**, OpenSearch **3.6**, fine-grained access control on.
- Embedding model: **Amazon Titan Text Embeddings v2** (`amazon.titan-embed-text-v2:0`), 1024 dimensions.
- Embedding happens **inside OpenSearch** (ingest pipeline for documents, `neural` query for search).
- Vector engine: `lucene`, space `cosinesimil` (nmslib is not available in 3.x).
- Hybrid (keyword + semantic) queries are **out of scope**. Queries here are neural-only.

Values in `<ANGLE_BRACKETS>` are the only things you fill in. Everything else is copy/paste.
The region and model match the plugin defaults (`bedrockregion` = `ap-southeast-2`,
`bedrockmodelid` = Titan v2, `bedrockdimension` = 1024). Change the region if yours differs.

| Placeholder | Meaning |
|---|---|
| `<DOMAIN_ENDPOINT>` | Domain endpoint, e.g. `https://search-xxxx.ap-southeast-2.es.amazonaws.com` |
| `<BEDROCK_INVOKE_ROLE_ARN>` | Role OpenSearch assumes to call Bedrock. This is the plugin's `bedrockrolearn` setting. |
| `<CONNECTOR_CREATOR_ROLE_ARN>` | Role you sign the connector-create call with (step 2 only). |

Console: run steps marked **Dev Tools** in OpenSearch Dashboards > Dev Tools. Step 2 uses `awscurl`.

---

## Step 0 - AWS prerequisites (once)

1. **Bedrock model access**: in the Bedrock console (same region), enable access to
   *Titan Text Embeddings V2*.
2. **Invoke role** (`<BEDROCK_INVOKE_ROLE_ARN>`).
   Trust policy:
   ```json
   {
     "Version": "2012-10-17",
     "Statement": [{
       "Effect": "Allow",
       "Principal": { "Service": "opensearchservice.amazonaws.com" },
       "Action": "sts:AssumeRole"
     }]
   }
   ```
   Permissions policy:
   ```json
   {
     "Version": "2012-10-17",
     "Statement": [{
       "Effect": "Allow",
       "Action": "bedrock:InvokeModel",
       "Resource": "arn:aws:bedrock:ap-southeast-2::foundation-model/amazon.titan-embed-text-v2:0"
     }]
   }
   ```
3. **Connector creator role** (`<CONNECTOR_CREATOR_ROLE_ARN>`), assumable by you. Permissions:
   ```json
   {
     "Version": "2012-10-17",
     "Statement": [
       { "Effect": "Allow", "Action": "iam:PassRole", "Resource": "<BEDROCK_INVOKE_ROLE_ARN>" },
       { "Effect": "Allow", "Action": "es:ESHttpPost", "Resource": "<DOMAIN_ARN>/*" }
     ]
   }
   ```
4. **Map the creator role in OpenSearch security**: Dashboards > Security > Roles > `ml_full_access` >
   Mapped users > add `<CONNECTOR_CREATOR_ROLE_ARN>` as a **backend role**. Also map the same
   backend role to `all_access` (or a role that can create indexes and pipelines) if you will run the
   later steps as this role.

**Expected:** nothing to verify yet. Step 2 fails with `403`/`PassRole` errors if this is wrong.

---

## Step 1 - Allow the Bedrock endpoint (Dev Tools)

```json
GET _cluster/settings?include_defaults=true&filter_path=*.plugins.ml_commons.trusted_connector_endpoints_regex,*.plugins.ml_commons.connector_access_control_enabled
```

**Expected:** the trusted regex includes `bedrock-runtime`. AWS domains normally ship with it. If it is
missing or empty, set it:

```json
PUT _cluster/settings
{
  "persistent": {
    "plugins.ml_commons.trusted_connector_endpoints_regex": [
      "^https://bedrock-runtime\\..*[a-z0-9-]\\.amazonaws\\.com/.*$"
    ]
  }
}
```

Also confirm ML is allowed to run remote models on any node (no ML node needed for remote models):

```json
GET _cluster/settings?include_defaults=true&filter_path=*.plugins.ml_commons.only_run_on_ml_node
```

**Expected:** any value is fine, remote models do not run locally.

---

## Step 2 - Create the Bedrock connector (`awscurl`, signed as the creator role)

Dev Tools cannot sign as an IAM role, so create the connector from a shell that has credentials for
`<CONNECTOR_CREATOR_ROLE_ARN>` (for example after `aws sts assume-role`):

```bash
awscurl --service es --region ap-southeast-2 -X POST \
  "<DOMAIN_ENDPOINT>/_plugins/_ml/connectors/_create" \
  -H 'Content-Type: application/json' \
  -d '{
  "name": "Bedrock Titan Text Embeddings v2 (moodle demo)",
  "description": "Titan v2 embeddings via Bedrock InvokeModel",
  "version": 1,
  "protocol": "aws_sigv4",
  "credential": { "roleArn": "<BEDROCK_INVOKE_ROLE_ARN>" },
  "parameters": {
    "region": "ap-southeast-2",
    "service_name": "bedrock",
    "model": "amazon.titan-embed-text-v2:0",
    "dimensions": 1024,
    "normalize": true,
    "embeddingTypes": ["float"]
  },
  "actions": [{
    "action_type": "predict",
    "method": "POST",
    "url": "https://bedrock-runtime.${parameters.region}.amazonaws.com/model/${parameters.model}/invoke",
    "headers": { "content-type": "application/json", "x-amz-content-sha256": "required" },
    "request_body": "{ \"inputText\": \"${parameters.inputText}\", \"dimensions\": ${parameters.dimensions}, \"normalize\": ${parameters.normalize}, \"embeddingTypes\": ${parameters.embeddingTypes} }",
    "pre_process_function": "connector.pre_process.bedrock.embedding",
    "post_process_function": "connector.post_process.bedrock_v2.embedding.float"
  }]
}'
```

**Expected:** `{"connector_id":"<CONNECTOR_ID>"}`. Keep the id.

---

## Step 3 - Register and deploy the remote model (Dev Tools)

```json
POST _plugins/_ml/models/_register
{
  "name": "bedrock-titan-embed-text-v2",
  "function_name": "remote",
  "description": "Titan v2 embeddings (moodle demo)",
  "connector_id": "<CONNECTOR_ID>"
}
```

**Expected:** `{"task_id":"...","status":"CREATED","model_id":"<MODEL_ID>"}`. Keep the model id.

```json
POST _plugins/_ml/models/<MODEL_ID>/_deploy
```

```json
GET _plugins/_ml/models/<MODEL_ID>
```

**Expected:** `"model_state": "DEPLOYED"`.

---

## Step 4 - Test the model end to end (Dev Tools)

```json
POST _plugins/_ml/_predict/text_embedding/<MODEL_ID>
{
  "text_docs": ["feeling stressed about exams"],
  "return_number": true,
  "target_response": ["sentence_embedding"]
}
```

**Expected:** a `sentence_embedding` with `"shape": [1024]`. If you get a Bedrock `AccessDenied`,
recheck step 0 (invoke role policy, trust policy, model access). A shape other than 1024 means the
connector `dimensions` parameter is wrong. **Do not continue until this passes.**

---

## Step 5 - Ingest pipeline (Dev Tools)

```json
PUT _ingest/pipeline/neural-pipeline-bedrock
{
  "description": "Embed Moodle chunk content with Titan v2",
  "processors": [{
    "text_embedding": {
      "model_id": "<MODEL_ID>",
      "field_map": { "content": "content_v" }
    }
  }]
}
```

Dry run:

```json
POST _ingest/pipeline/neural-pipeline-bedrock/_simulate
{ "docs": [{ "_source": { "content": "hello world" } }] }
```

**Expected:** the returned document has `content_v` with 1024 numbers.

---

## Step 6 - Create the semantic demo index (Dev Tools)

Same fields as the plugin's keyword index (`document.php`) plus `content_v`. Indexed one document per
chunk, so `chunk_number` / `chunk_total` / `original_id` are included.

```json
PUT semantic_moodle_demo
{
  "settings": {
    "index.knn": true,
    "default_pipeline": "neural-pipeline-bedrock",
    "number_of_shards": 1,
    "number_of_replicas": 1
  },
  "mappings": {
    "_meta": {
      "embedding_model": "amazon.titan-embed-text-v2:0",
      "embedding_dimension": 1024
    },
    "properties": {
      "id":           { "type": "keyword" },
      "parentid":     { "type": "keyword" },
      "original_id":  { "type": "keyword" },
      "itemid":       { "type": "integer" },
      "title":        { "type": "text" },
      "content":      { "type": "text" },
      "contextid":    { "type": "integer" },
      "areaid":       { "type": "keyword" },
      "type":         { "type": "integer" },
      "courseid":     { "type": "integer" },
      "owneruserid":  { "type": "integer" },
      "userid":       { "type": "integer" },
      "groupid":      { "type": "integer" },
      "modified":     { "type": "date" },
      "chunk_number": { "type": "integer" },
      "chunk_total":  { "type": "integer" },
      "content_v": {
        "type": "knn_vector",
        "dimension": 1024,
        "method": {
          "name": "hnsw",
          "engine": "lucene",
          "space_type": "cosinesimil"
        }
      }
    }
  }
}
```

**Expected:** `{"acknowledged":true,...}`.

---

## Step 7 - Index sample documents (Dev Tools)

Eight hand-written documents across three courses. Course 2 and 3 are "in scope"; course 4 stands in
for an out-of-scope course (so it is **not** indexed here, see step 9). Documents deliberately share
meaning with the queries without sharing keywords.

```json
POST semantic_moodle_demo/_bulk?refresh=wait_for
{ "index": { "_id": "c2-1" } }
{ "id": "c2-1", "parentid": "c2-1", "itemid": 1, "title": "Wellbeing support", "content": "Students who feel overwhelmed can contact counselling services and peer mentors for help.", "contextid": 20, "areaid": "mod_forum-post", "type": 1, "courseid": 2, "owneruserid": 5, "modified": "2026-01-10T00:00:00Z", "chunk_number": 1, "chunk_total": 1 }
{ "index": { "_id": "c2-2" } }
{ "id": "c2-2", "parentid": "c2-2", "itemid": 2, "title": "Assessment timetable", "content": "Final examinations run in week 14. Submit extension requests two days before the due date.", "contextid": 20, "areaid": "mod_page-activity", "type": 1, "courseid": 2, "owneruserid": 5, "modified": "2026-01-11T00:00:00Z", "chunk_number": 1, "chunk_total": 1 }
{ "index": { "_id": "c2-3" } }
{ "id": "c2-3", "parentid": "c2-3", "itemid": 3, "title": "Lab safety", "content": "Always wear protective eyewear and tie back long hair when operating machinery in the workshop.", "contextid": 21, "areaid": "mod_page-activity", "type": 1, "courseid": 2, "owneruserid": 6, "modified": "2026-01-12T00:00:00Z", "chunk_number": 1, "chunk_total": 1 }
{ "index": { "_id": "c3-1" } }
{ "id": "c3-1", "parentid": "c3-1", "itemid": 4, "title": "Growing tomatoes", "content": "Plant seedlings after the last frost and water them deeply a few times a week.", "contextid": 30, "areaid": "mod_page-activity", "type": 1, "courseid": 3, "owneruserid": 7, "modified": "2026-01-13T00:00:00Z", "chunk_number": 1, "chunk_total": 1 }
{ "index": { "_id": "c3-2" } }
{ "id": "c3-2", "parentid": "c3-2", "itemid": 5, "title": "Composting basics", "content": "Kitchen scraps and dry leaves break down into rich soil amendment over several months.", "contextid": 30, "areaid": "mod_forum-post", "type": 1, "courseid": 3, "owneruserid": 7, "modified": "2026-01-14T00:00:00Z", "chunk_number": 1, "chunk_total": 1 }
{ "index": { "_id": "c3-3" } }
{ "id": "c3-3", "parentid": "c3-3", "itemid": 6, "title": "Mental health first aid", "content": "Recognise signs of anxiety and low mood in classmates and know how to reach out safely.", "contextid": 31, "areaid": "mod_forum-post", "type": 1, "courseid": 3, "owneruserid": 8, "modified": "2026-01-15T00:00:00Z", "chunk_number": 1, "chunk_total": 1 }
{ "index": { "_id": "c3-4" } }
{ "id": "c3-4", "parentid": "c3-4", "itemid": 7, "title": "Library opening hours", "content": "The library is open until midnight during the examination period.", "contextid": 31, "areaid": "mod_page-activity", "type": 1, "courseid": 3, "owneruserid": 8, "modified": "2026-01-16T00:00:00Z", "chunk_number": 1, "chunk_total": 1 }
{ "index": { "_id": "c2-4" } }
{ "id": "c2-4", "parentid": "c2-4", "itemid": 8, "title": "Group project roles", "content": "Each team nominates a coordinator, a recorder and a presenter before the first meeting.", "contextid": 22, "areaid": "mod_forum-post", "type": 1, "courseid": 2, "owneruserid": 5, "modified": "2026-01-17T00:00:00Z", "chunk_number": 1, "chunk_total": 1 }
```

**Expected:** `"errors": false` with 8 items. Spot check that vectors were generated by the pipeline:

```json
GET semantic_moodle_demo/_doc/c2-1?_source_includes=title,content_v
```

**Expected:** `content_v` has 1024 values. Also:

```json
GET semantic_moodle_demo/_count
```

**Expected:** `"count": 8`.

---

## Step 8 - Neural search (Dev Tools)

Semantic-only match (no shared keywords with the documents):

```json
GET semantic_moodle_demo/_search
{
  "size": 5,
  "_source": ["title", "content", "courseid"],
  "query": {
    "neural": {
      "content_v": {
        "query_text": "feeling stressed",
        "model_id": "<MODEL_ID>",
        "k": 5
      }
    }
  }
}
```

**Expected:** `Wellbeing support` and `Mental health first aid` rank at or near the top, ahead of
`Growing tomatoes` and `Lab safety`.

Compare with keyword search (same words):

```json
GET semantic_moodle_demo/_search
{
  "size": 5,
  "_source": ["title", "courseid"],
  "query": { "match": { "content": "feeling stressed" } }
}
```

**Expected:** zero or irrelevant hits. This is the gap semantic search fills.

Second query, `"how do I plant vegetables"`: **Expected** `Growing tomatoes` first, `Composting basics`
next.

---

## Step 9 - Prove filtering (scope and access) works inside the vector query (Dev Tools)

Course filter (stands in for scope and course selection). Only course 3 is allowed:

```json
GET semantic_moodle_demo/_search
{
  "size": 5,
  "_source": ["title", "courseid"],
  "query": {
    "neural": {
      "content_v": {
        "query_text": "feeling stressed",
        "model_id": "<MODEL_ID>",
        "k": 5,
        "filter": { "terms": { "courseid": [3] } }
      }
    }
  }
}
```

**Expected:** only `courseid: 3` documents, `Mental health first aid` first. `Wellbeing support` (course 2)
must **not** appear, even though it is a strong semantic match.

Access-style filter (context ids the user may see, mirrors `execute_query` access filtering):

```json
GET semantic_moodle_demo/_search
{
  "size": 5,
  "_source": ["title", "contextid"],
  "query": {
    "neural": {
      "content_v": {
        "query_text": "feeling stressed",
        "model_id": "<MODEL_ID>",
        "k": 5,
        "filter": {
          "bool": {
            "filter": [
              { "terms": { "courseid": [2, 3] } },
              { "terms": { "contextid": [30, 31] } }
            ]
          }
        }
      }
    }
  }
}
```

**Expected:** only contexts 30 and 31.

Out-of-scope course (course 4 was never indexed):

```json
GET semantic_moodle_demo/_search
{
  "size": 5,
  "_source": ["title", "courseid"],
  "query": {
    "neural": {
      "content_v": {
        "query_text": "feeling stressed",
        "model_id": "<MODEL_ID>",
        "k": 5,
        "filter": { "terms": { "courseid": [4] } }
      }
    }
  }
}
```

**Expected:** `"hits": []`.

---

## Step 10 - Clean up (Dev Tools, optional)

```json
DELETE semantic_moodle_demo
DELETE _ingest/pipeline/neural-pipeline-bedrock
POST _plugins/_ml/models/<MODEL_ID>/_undeploy
DELETE _plugins/_ml/models/<MODEL_ID>
DELETE _plugins/_ml/connectors/<CONNECTOR_ID>
```

---

## What the plugin will automate

The admin setup page (`semanticsetup.php`) will run steps 2, 3, 5 and 6 idempotently using the plugin
settings (`bedrockrolearn`, `bedrockregion`, `bedrockmodelid`, `bedrockdimension`), checking each step
first and creating it only if missing. Index name is `semantic_<index>`; the pipeline, connector and model
names derive from the same settings. The embedding model and dimension are stored in the index
`_meta`; a mismatch with the settings requires an explicit rebuild.

Requirement to confirm from step 2: the connector call needs IAM `iam:PassRole` and an `ml_full_access`
backend-role mapping, so the credentials the plugin signs with (`signingkeyid` / `signingsecretkey`)
must belong to a principal like `<CONNECTOR_CREATOR_ROLE_ARN>`.
