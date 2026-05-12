ALTER TABLE "oai_resumption"
  ADD COLUMN token varchar(255) DEFAULT NULL;

CREATE UNIQUE INDEX token ON "oai_resumption" (token);
