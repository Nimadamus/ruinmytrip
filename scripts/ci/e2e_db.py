"""DB handle for the e2e scripts: a SQLite path (the default local run) or a postgresql:// URL, so
the same journeys can run against a Postgres staging copy that matches production's engine.
Only what the scripts use: connect(target).execute(sql, args).fetchall(), commit(), close()."""
import sqlite3


class _Pg:
    def __init__(self, url):
        import psycopg
        self.c = psycopg.connect(url)

    def execute(self, sql, args=()):
        cur = self.c.cursor()
        cur.execute(sql.replace('?', '%s'), tuple(args))
        return _Rows(cur)

    def commit(self):
        self.c.commit()

    def close(self):
        self.c.close()


class _Rows:
    def __init__(self, cur):
        self.cur = cur

    def fetchall(self):
        return self.cur.fetchall() if self.cur.description else []

    def fetchone(self):
        return self.cur.fetchone() if self.cur.description else None


def connect(target):
    return _Pg(target) if str(target).startswith(('postgres://', 'postgresql://')) else sqlite3.connect(target)
