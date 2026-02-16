const { z } = require('zod');

const FLAG_REGEX = /^NFSUCTF\{.*\}$/;

const submitFlagSchema = z.object({
    challengeId: z.number().int().positive('Invalid challenge ID'),
    flag: z.string()
        .min(1, 'Flag is required')
        .transform(v => v.trim())
        .refine(v => FLAG_REGEX.test(v), { message: 'Invalid flag format. Flags must match NFSUCTF{...}' }),
});

const createChallengeSchema = z.object({
    title: z.string().min(1).max(255),
    description: z.string().max(10000).optional().default(''),
    category: z.string().min(1).max(100),
    points: z.number().int().positive().max(10000),
    flag: z.string().min(1, 'Flag is required'),
    isActive: z.boolean().optional().default(true),
    maxAttempts: z.number().int().positive().nullable().optional().default(null),
});

const updateChallengeSchema = z.object({
    title: z.string().min(1).max(255).optional(),
    description: z.string().max(10000).optional(),
    category: z.string().min(1).max(100).optional(),
    points: z.number().int().positive().max(10000).optional(),
    flag: z.string().min(1).optional(),
    isActive: z.boolean().optional(),
    maxAttempts: z.number().int().positive().nullable().optional(),
});

module.exports = { submitFlagSchema, createChallengeSchema, updateChallengeSchema, FLAG_REGEX };
