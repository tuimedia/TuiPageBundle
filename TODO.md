X-TYPESENSE-API-KEY=abcd1234

## Simplify TranslatedPage

* Can't be nested
* Should include tags as facet type string[]
* Transformers responsible for adding searchable text and defining facets and other fields
  * WFP example: whether page contains a video block with availableOffline: true

* Ditch mapping config
* Ditch translated page
* Register search transformers (per component?) tagged
  * index: receive typesense JSON document, data & langData, return json document
  * setup: return typesense collection fields for component

## Batch

POST /collections/{name}/documents/import
  ?action=create|upsert|update
  &dirty_values=coerce_or_reject|reject|coerce_or_drop|drop

Document body must be JSONL (one JSON object per line), no commas, 40 inserted at a time (change default batch size)

Response has a result per input line, batch isn't atomic, so check each line for errors

## Create

POST /collections/{name}/documents

## Update, Delete

PATCH|DELETE /collections/{name}/documents/{id}

## Create index

POST /collections
{
 "name": "companies",
 "fields": [
   {"name": "company_name", "type": "string" },
   {"name": "num_employees", "type": "int32" },
   {"name": "country", "type": "string", "facet": true }
 ],
 "default_sorting_field": "num_employees"
}

field schema:
  string name, enum type, bool optional, bool facet, bool index
  facets are indexed without being tokenised.
  name can be a regex

types:
  string
  int32
  int64
  float
  bool
  geopoint

  string[]
  int32[]
  int64[]
  float[]
  bool[]

  auto
  string* (cast to string)

unnamed fields are stored (and returned in results) but not indexed, searchable

{"name": ".*", "type": "auto" } -- auto schema detection
