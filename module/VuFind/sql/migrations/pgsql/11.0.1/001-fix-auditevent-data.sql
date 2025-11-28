UPDATE audit_event SET data = (data #>> '{}')::json;
