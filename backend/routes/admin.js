const express = require('express');
const router = express.Router();
const adminController = require('../controllers/admin');
const { verifyToken, verifyAdmin } = require('../middleware/auth');

// All admin routes require token + admin role
router.use(verifyToken, verifyAdmin);

// Team management
router.get('/teams', adminController.listTeams);
router.post('/teams', adminController.createTeam);
router.put('/teams/:teamId/password', adminController.resetPassword);
router.post('/teams/bonus', adminController.addBonusPoints);
router.put('/teams/:teamId/toggle', adminController.toggleTeamActive);

// Challenge management
router.get('/challenges', adminController.listAllChallenges);
router.post('/challenges', adminController.createChallenge);
router.put('/challenges/:id', adminController.updateChallenge);
router.delete('/challenges/:id', adminController.deleteChallenge);
router.post('/challenges/:id/files', adminController.upload.single('file'), adminController.uploadChallengeFile);
router.delete('/files/:fileId', adminController.deleteChallengeFile);

// Event control
router.post('/event/control', adminController.controlEvent);
router.put('/event/timing', adminController.updateEventTiming);

// Announcements
router.post('/announcements', adminController.createAnnouncement);
router.put('/announcements/:id', adminController.updateAnnouncement);
router.delete('/announcements/:id', adminController.deleteAnnouncement);

// Site content
router.put('/content', adminController.updateSiteContent);

// Analytics
router.get('/analytics', adminController.getAnalytics);
router.get('/submissions', adminController.getSubmissions);
router.get('/logs', adminController.getAdminLogs);
router.get('/suspicious', adminController.getSuspiciousActivity);

// Emergency overrides
router.post('/solve/manual', adminController.manualSolve);
router.post('/solve/remove', adminController.removeSolve);
router.post('/points/edit', adminController.editTeamPoints);
router.post('/recalculate', adminController.recalculate);

// Exports
router.get('/export/:type', adminController.exportCSV);

module.exports = router;
