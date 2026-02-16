const { z } = require('zod');

const createTeamSchema = z.object({
    name: z.string().min(1).max(100),
    username: z.string().min(3).max(100)
        .regex(/^[a-zA-Z0-9_-]+$/, 'Username can only contain letters, numbers, underscores, and hyphens'),
    password: z.string().min(6).max(255),
    role: z.enum(['team', 'admin']).optional().default('team'),
});

const bonusPointsSchema = z.object({
    teamId: z.string().uuid(),
    points: z.number().int(),
    reason: z.string().min(1).max(500),
});

const eventControlSchema = z.object({
    action: z.enum([
        'start', 'stop', 'freeze', 'unfreeze', 'archive', 'unarchive',
        'maintenance_on', 'maintenance_off', 'reset',
    ]),
});

const eventTimingSchema = z.object({
    name: z.string().min(1).max(255).optional(),
    startTime: z.string().datetime().nullable().optional(),
    endTime: z.string().datetime().nullable().optional(),
});

const announcementSchema = z.object({
    title: z.string().min(1).max(255),
    content: z.string().min(1).max(5000),
    isPinned: z.boolean().optional().default(false),
});

const siteContentSchema = z.object({
    contentKey: z.string().min(1).max(100),
    contentValue: z.string().min(1).max(50000),
});

const manualSolveSchema = z.object({
    teamId: z.string().uuid(),
    challengeId: z.number().int().positive(),
    points: z.number().int().optional(),
});

const editPointsSchema = z.object({
    teamId: z.string().uuid(),
    points: z.number().int(),
    reason: z.string().min(1).max(500),
});

const paginationSchema = z.object({
    page: z.coerce.number().int().positive().default(1),
    limit: z.coerce.number().int().positive().max(100).default(20),
});

module.exports = {
    createTeamSchema,
    bonusPointsSchema,
    eventControlSchema,
    eventTimingSchema,
    announcementSchema,
    siteContentSchema,
    manualSolveSchema,
    editPointsSchema,
    paginationSchema,
};
