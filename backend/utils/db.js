const { Pool } = require('pg');
const logger = require('./logger');

const pool = new Pool({
    connectionString: process.env.DATABASE_URL,
    ssl: { rejectUnauthorized: false },
    max: 20,
    idleTimeoutMillis: 30000,
    connectionTimeoutMillis: 10000,
});

pool.on('error', (err) => {
    logger.error({ err }, 'Unexpected database pool error');
});

pool.on('connect', () => {
    logger.debug('New database connection established');
});

/**
 * Execute a query against the database
 */
async function query(text, params) {
    const start = Date.now();
    try {
        const result = await pool.query(text, params);
        const duration = Date.now() - start;
        logger.debug({ query: text.substring(0, 80), duration, rows: result.rowCount }, 'Query executed');
        return result;
    } catch (err) {
        logger.error({ err, query: text.substring(0, 80) }, 'Query error');
        throw err;
    }
}

/**
 * Get a client from the pool for transactions
 */
async function getClient() {
    const client = await pool.connect();
    const originalQuery = client.query.bind(client);
    const originalRelease = client.release.bind(client);

    // Monkey-patch release to ensure it's only called once
    let released = false;
    client.release = () => {
        if (!released) {
            released = true;
            originalRelease();
        }
    };

    return client;
}

/**
 * Execute operations within a transaction
 */
async function withTransaction(callback) {
    const client = await getClient();
    try {
        await client.query('BEGIN');
        const result = await callback(client);
        await client.query('COMMIT');
        return result;
    } catch (err) {
        await client.query('ROLLBACK');
        throw err;
    } finally {
        client.release();
    }
}

module.exports = { query, getClient, withTransaction, pool };
