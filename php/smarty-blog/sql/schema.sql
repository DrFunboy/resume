CREATE TABLE IF NOT EXISTS categories (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(255) NOT NULL,
    description TEXT NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS articles (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    title         VARCHAR(255) NOT NULL,
    description   TEXT NULL,
    content       LONGTEXT NULL,
    image         VARCHAR(500) NULL,
    views         INT NOT NULL DEFAULT 0,
    published_at  DATETIME NOT NULL,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS article_category (
    article_id  INT NOT NULL,
    category_id INT NOT NULL,
    PRIMARY KEY (article_id, category_id),
    CONSTRAINT fk_ac_article  FOREIGN KEY (article_id)  REFERENCES articles(id)   ON DELETE CASCADE,
    CONSTRAINT fk_ac_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_articles_published_at ON articles(published_at);
CREATE INDEX idx_articles_views ON articles(views);
