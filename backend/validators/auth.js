const { z } = require('zod');

const loginSchema = z.object({
    username: z.string().min(1, 'Username is required').max(100),
    password: z.string().min(1, 'Password is required').max(255),
});

const registerSchema = z.object({
    name: z.string().min(1, 'Team name is required').max(100),
    username: z.string().min(3, 'Username must be at least 3 characters').max(100)
        .regex(/^[a-zA-Z0-9_-]+$/, 'Username can only contain letters, numbers, underscores, and hyphens'),
    password: z.string().min(6, 'Password must be at least 6 characters').max(255),
});

module.exports = { loginSchema, registerSchema };
