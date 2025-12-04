<?php
/**
 * Gamification Helper Functions
 */

// Update streak setelah absen
function updateStreak($conn, $user_id) {
    $today = date('Y-m-d');
    
    // Get user's last attendance date
    $stmt = mysqli_prepare($conn, "SELECT last_attendance_date, current_streak, longest_streak FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    
    $last_date = $user['last_attendance_date'];
    $current_streak = (int)$user['current_streak'];
    $longest_streak = (int)$user['longest_streak'];
    
    if ($last_date === $today) {
        // Already attended today, no change
        return $current_streak;
    }
    
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    
    if ($last_date === $yesterday) {
        // Consecutive day - increase streak
        $current_streak++;
    } else {
        // Streak broken - reset to 1
        $current_streak = 1;
    }
    
    // Update longest streak if current is higher
    if ($current_streak > $longest_streak) {
        $longest_streak = $current_streak;
    }
    
    // Update user
    $stmt = mysqli_prepare($conn, "UPDATE users SET current_streak = ?, longest_streak = ?, last_attendance_date = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "iisi", $current_streak, $longest_streak, $today, $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    return $current_streak;
}

// Check dan award badges
function checkAndAwardBadges($conn, $user_id) {
    $awarded = [];
    
    // Get user stats
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as total FROM absen WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $total_attendance = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'];
    mysqli_stmt_close($stmt);
    
    $stmt = mysqli_prepare($conn, "SELECT current_streak, longest_streak FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $user = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    
    $current_streak = (int)$user['current_streak'];
    
    // Get all badges user doesn't have yet
    $result = mysqli_query($conn, "
        SELECT b.* FROM badges b 
        WHERE b.id NOT IN (SELECT badge_id FROM user_badges WHERE user_id = $user_id)
    ");
    
    while ($badge = mysqli_fetch_assoc($result)) {
        $should_award = false;
        
        switch ($badge['requirement_type']) {
            case 'attendance_count':
                if ($total_attendance >= $badge['requirement_value']) {
                    $should_award = true;
                }
                break;
            case 'streak_days':
                if ($current_streak >= $badge['requirement_value']) {
                    $should_award = true;
                }
                break;
        }
        
        if ($should_award) {
            $stmt = mysqli_prepare($conn, "INSERT IGNORE INTO user_badges (user_id, badge_id) VALUES (?, ?)");
            mysqli_stmt_bind_param($stmt, "ii", $user_id, $badge['id']);
            if (mysqli_stmt_execute($stmt)) {
                $awarded[] = $badge;
            }
            mysqli_stmt_close($stmt);
        }
    }
    
    return $awarded;
}

// Award early bird badge
function checkEarlyBird($conn, $user_id, $event_id) {
    // Check if this is the first attendance for this event
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as c FROM absen WHERE event_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $event_id);
    mysqli_stmt_execute($stmt);
    $count = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'];
    mysqli_stmt_close($stmt);
    
    if ($count == 1) { // This user is the first one
        // Get early_bird badge
        $badge = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM badges WHERE code = 'early_bird'"));
        if ($badge) {
            $stmt = mysqli_prepare($conn, "INSERT IGNORE INTO user_badges (user_id, badge_id) VALUES (?, ?)");
            mysqli_stmt_bind_param($stmt, "ii", $user_id, $badge['id']);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            return true;
        }
    }
    return false;
}

// Get user badges
function getUserBadges($conn, $user_id) {
    $stmt = mysqli_prepare($conn, "
        SELECT b.*, ub.earned_at 
        FROM user_badges ub 
        JOIN badges b ON ub.badge_id = b.id 
        WHERE ub.user_id = ? 
        ORDER BY ub.earned_at DESC
    ");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $badges = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $badges[] = $row;
    }
    mysqli_stmt_close($stmt);
    
    return $badges;
}

// Get leaderboard
function getLeaderboard($conn, $limit = 10) {
    $result = mysqli_query($conn, "
        SELECT u.id, u.nama, u.nim, u.kelas, u.picture, u.current_streak, u.longest_streak,
               COUNT(a.id) as total_attendance,
               (SELECT COUNT(*) FROM user_badges WHERE user_id = u.id) as badge_count
        FROM users u
        LEFT JOIN absen a ON u.id = a.user_id
        GROUP BY u.id
        ORDER BY total_attendance DESC, badge_count DESC, longest_streak DESC
        LIMIT $limit
    ");
    
    $leaderboard = [];
    $rank = 1;
    while ($row = mysqli_fetch_assoc($result)) {
        $row['rank'] = $rank++;
        $leaderboard[] = $row;
    }
    
    return $leaderboard;
}

// Get user rank
function getUserRank($conn, $user_id) {
    // Get user's attendance count
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as cnt FROM absen WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $my_count = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cnt'];
    mysqli_stmt_close($stmt);
    
    // Count users with more attendance
    $stmt = mysqli_prepare($conn, "
        SELECT COUNT(DISTINCT user_id) as higher
        FROM (
            SELECT user_id, COUNT(*) as cnt 
            FROM absen 
            GROUP BY user_id 
            HAVING cnt > ?
        ) t
    ");
    mysqli_stmt_bind_param($stmt, "i", $my_count);
    mysqli_stmt_execute($stmt);
    $higher = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['higher'] ?? 0;
    mysqli_stmt_close($stmt);
    
    return $higher + 1;
}
?>
