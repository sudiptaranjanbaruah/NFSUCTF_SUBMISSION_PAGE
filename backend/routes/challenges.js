const express = require('express');
const router = express.Router();
const challengeController = require('../controllers/challenges');
const { verifyToken, verifyEventLive } = require('../middleware/auth');
const { submissionLimiter } = require('../middleware/rateLimiter');

router.get('/challenges', verifyToken, challengeController.listChallenges);
router.get('/challenges/:id', verifyToken, challengeController.getChallenge);
router.get('/challenges/:id/stats', verifyToken, challengeController.getChallengeStats);
router.post('/submit', verifyToken, verifyEventLive, submissionLimiter, challengeController.submitFlag);

module.exports = router;
