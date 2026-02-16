const logger = require('../utils/logger');

function errorHandler(err, req, res, _next) {
    logger.error({
        err,
        method: req.method,
        url: req.originalUrl,
        ip: req.ip,
    }, 'Unhandled error');

    // Zod validation errors
    if (err.name === 'ZodError') {
        return res.status(400).json({
            message: 'Validation error',
            errors: err.errors.map(e => ({ path: e.path.join('.'), message: e.message })),
        });
    }

    // PostgreSQL unique constraint violation
    if (err.code === '23505') {
        return res.status(409).json({ message: 'Resource already exists' });
    }

    // PostgreSQL foreign key violation
    if (err.code === '23503') {
        return res.status(400).json({ message: 'Referenced resource does not exist' });
    }

    const statusCode = err.statusCode || 500;
    const message = process.env.NODE_ENV === 'production' && statusCode === 500
        ? 'Internal server error'
        : err.message || 'Internal server error';

    res.status(statusCode).json({ message });
}

module.exports = errorHandler;
