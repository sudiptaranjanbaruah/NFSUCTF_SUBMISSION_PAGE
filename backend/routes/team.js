const express = require('express');
const router = express.Router();
const teamController = require('../controllers/team');
const { verifyToken } = require('../middleware/auth');

router.get('/team/profile', verifyToken, teamController.getProfile);

module.exports = router;
