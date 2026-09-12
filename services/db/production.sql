-- Production integration primitives. Safe to apply repeatedly.
CREATE TABLE IF NOT EXISTS service_outbox (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  aggregate_type VARCHAR(48) NOT NULL,
  aggregate_id BIGINT UNSIGNED NOT NULL,
  event_type VARCHAR(96) NOT NULL,
  payload JSON NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  published_at DATETIME NULL DEFAULT NULL,
  attempts INT UNSIGNED NOT NULL DEFAULT 0,
  last_error VARCHAR(512) NULL DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_outbox_pending (published_at, id),
  KEY idx_outbox_aggregate (aggregate_type, aggregate_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Database triggers make event creation atomic with both legacy-PHP and
-- microservice writes. The worker below is responsible for delivery/retry.
DROP TRIGGER IF EXISTS trg_notes_outbox_insert;
DROP TRIGGER IF EXISTS trg_notes_outbox_update;
DROP TRIGGER IF EXISTS trg_note_labels_outbox_insert;
DROP TRIGGER IF EXISTS trg_note_labels_outbox_delete;
DROP TRIGGER IF EXISTS trg_attachments_outbox_insert;
DROP TRIGGER IF EXISTS trg_attachments_outbox_delete;

DELIMITER //
CREATE TRIGGER trg_notes_outbox_insert AFTER INSERT ON notes FOR EACH ROW
BEGIN
  INSERT INTO service_outbox (aggregate_type, aggregate_id, event_type, payload)
  VALUES ('note', NEW.note_id, 'note.created', JSON_OBJECT('note_id', NEW.note_id, 'user_id', NEW.user_id, 'title', NEW.title));
END//
CREATE TRIGGER trg_notes_outbox_update AFTER UPDATE ON notes FOR EACH ROW
BEGIN
  INSERT INTO service_outbox (aggregate_type, aggregate_id, event_type, payload)
  VALUES ('note', NEW.note_id,
    IF(NEW.archived = 1 AND OLD.archived = 0, 'note.archived', 'note.updated'),
    JSON_OBJECT('note_id', NEW.note_id, 'user_id', NEW.user_id, 'updated_at', NEW.updated_at));
END//
CREATE TRIGGER trg_note_labels_outbox_insert AFTER INSERT ON note_labels FOR EACH ROW
BEGIN
  INSERT INTO service_outbox (aggregate_type, aggregate_id, event_type, payload)
  VALUES ('note', NEW.note_id, 'note.label_attached', JSON_OBJECT('note_id', NEW.note_id, 'label_id', NEW.label_id));
END//
CREATE TRIGGER trg_note_labels_outbox_delete AFTER DELETE ON note_labels FOR EACH ROW
BEGIN
  INSERT INTO service_outbox (aggregate_type, aggregate_id, event_type, payload)
  VALUES ('note', OLD.note_id, 'note.label_detached', JSON_OBJECT('note_id', OLD.note_id, 'label_id', OLD.label_id));
END//
CREATE TRIGGER trg_attachments_outbox_insert AFTER INSERT ON note_attachments FOR EACH ROW
BEGIN
  INSERT INTO service_outbox (aggregate_type, aggregate_id, event_type, payload)
  VALUES ('attachment', NEW.attachment_id, 'attachment.created', JSON_OBJECT('attachment_id', NEW.attachment_id, 'note_id', NEW.note_id, 'mime_type', NEW.mime_type));
END//
CREATE TRIGGER trg_attachments_outbox_delete AFTER DELETE ON note_attachments FOR EACH ROW
BEGIN
  INSERT INTO service_outbox (aggregate_type, aggregate_id, event_type, payload)
  VALUES ('attachment', OLD.attachment_id, 'attachment.deleted', JSON_OBJECT('attachment_id', OLD.attachment_id, 'note_id', OLD.note_id));
END//
DELIMITER ;
