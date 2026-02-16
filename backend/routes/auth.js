const express = require('express');
const router = express.Router();
const authController = require('../controllers/auth');
const { verifyToken } = require('../middleware/auth');
const { loginLimiter } = require('../middleware/rateLimiter');

router.post('/login', loginLimiter, authController.login);
router.post('/admin/login', loginLimiter, authController.adminLogin);
router.post('/logout', authController.logout);
router.get('/me', verifyToken, authController.me);

module.exports = router;
