const bcrypt = require('bcryptjs');
const jwt = require('jsonwebtoken');
const db = require('../utils/db');
const logger = require('../utils/logger');
const { loginSchema, registerSchema } = require('../validators/auth');

const JWT_EXPIRES_IN = '3h';

async function login(req, res, next) {
    try {
        const { username, password } = loginSchema.parse(req.body);

        const result = await db.query(
            'SELECT id, name, username, password_hash, role, is_active FROM teams WHERE username = $1',
            [username]
        );
        const team = result.rows[0];

        if (!team) {
            return res.status(401).json({ message: 'Invalid credentials' });
        }

        if (!team.is_active) {
            return res.status(403).json({ message: 'Account has been deactivated' });
        }

        // Check maintenance mode for non-admins
        if (team.role === 'team') {
            const eventResult = await db.query('SELECT maintenance_mode FROM events ORDER BY id DESC LIMIT 1');
            if (eventResult.rows[0]?.maintenance_mode) {
                return res.status(503).json({ message: 'System is under maintenance. Please try again later.' });
            }
        }

        const valid = await bcrypt.compare(password, team.password_hash);
        if (!valid) {
            return res.status(401).json({ message: 'Invalid credentials' });
        }

        const token = jwt.sign(
            { teamId: team.id, name: team.name, role: team.role },
            process.env.JWT_SECRET,
            { expiresIn: JWT_EXPIRES_IN }
        );

        res.cookie('token', token, {
            httpOnly: true,
            secure: process.env.NODE_ENV === 'production',
            sameSite: 'strict',
            maxAge: 3 * 60 * 60 * 1000,
        });

        logger.info({ teamId: team.id, role: team.role }, 'Login successful');

        res.json({
            message: 'Login successful',
            team: { id: team.id, name: team.name, role: team.role },
        });
    } catch (err) {
        next(err);
    }
}

async function adminLogin(req, res, next) {
    try {
        const { username, password } = loginSchema.parse(req.body);

        const result = await db.query(
            'SELECT id, name, username, password_hash, role, is_active FROM teams WHERE username = $1 AND role IN ($2, $3)',
            [username, 'admin', 'super_admin']
        );
        const admin = result.rows[0];

        if (!admin) {
            return res.status(401).json({ message: 'Invalid admin credentials' });
        }

        if (!admin.is_active) {
            return res.status(403).json({ message: 'Admin account has been deactivated' });
        }

        const valid = await bcrypt.compare(password, admin.password_hash);
        if (!valid) {
            return res.status(401).json({ message: 'Invalid admin credentials' });
        }

        const token = jwt.sign(
            { teamId: admin.id, name: admin.name, role: admin.role },
            process.env.JWT_SECRET,
            { expiresIn: JWT_EXPIRES_IN }
        );

        res.cookie('token', token, {
            httpOnly: true,
            secure: process.env.NODE_ENV === 'production',
            sameSite: 'strict',
            maxAge: 3 * 60 * 60 * 1000,
        });

        logger.info({ adminId: admin.id }, 'Admin login successful');

        res.json({
            message: 'Admin login successful',
            team: { id: admin.id, name: admin.name, role: admin.role },
        });
    } catch (err) {
        next(err);
    }
}

async function logout(req, res) {
    res.clearCookie('token', {
        httpOnly: true,
        secure: process.env.NODE_ENV === 'production',
        sameSite: 'strict',
    });
    res.json({ message: 'Logged out successfully' });
}

async function me(req, res, next) {
    try {
        const result = await db.query(
            'SELECT id, name, username, role, total_points, total_solves, last_solve_time, created_at FROM teams WHERE id = $1',
            [req.team.teamId]
        );
        const team = result.rows[0];
        if (!team) {
            return res.status(404).json({ message: 'Team not found' });
        }
        res.json({ team });
    } catch (err) {
        next(err);
    }
}

module.exports = { login, adminLogin, logout, me };
