const rateLimit = require('express-rate-limit');

/**
 * Rate limiter for login attempts
 */
const loginLimiter = rateLimit({
    windowMs: parseInt(process.env.LOGIN_RATE_LIMIT_WINDOW_MS) || 15 * 60 * 1000,
    max: parseInt(process.env.LOGIN_RATE_LIMIT_MAX) || 10,
    message: { message: 'Too many login attempts. Please try again later.' },
    standardHeaders: true,
    legacyHeaders: false,
    keyGenerator: (req) => req.ip,
});

/**
 * Rate limiter for flag submissions (cooldown)
 */
const submissionLimiter = rateLimit({
    windowMs: parseInt(process.env.SUBMISSION_COOLDOWN_MS) || 5000,
    max: 1,
    message: { message: 'Please wait before submitting again.' },
    standardHeaders: true,
    legacyHeaders: false,
    keyGenerator: (req) => `${req.team?.teamId || req.ip}`,
});

/**
 * General API rate limiter
 */
const apiLimiter = rateLimit({
    windowMs: 60 * 1000,
    max: 100,
    message: { message: 'Too many requests. Please slow down.' },
    standardHeaders: true,
    legacyHeaders: false,
});

module.exports = { loginLimiter, submissionLimiter, apiLimiter };
