ALTER TABLE "oai_resumption"
  ADD COLUMN token varchar(255) DEFAULT NULL;

CREATE UNIQUE INDEX oai_resumption_token_idx ON "oai_resumption" (token);
