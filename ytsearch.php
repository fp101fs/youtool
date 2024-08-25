<?php
function youtubeSearch($query, $minDuration = 60, $maxDuration = 180, $targetResults = 5) {
    $results = [];
    $continuationToken = null;

    while (count(array_filter($results, function($v) { return $v['highlight']; })) < $targetResults) {
        $searchUrl = "https://www.youtube.com/results?search_query=" . urlencode($query);
        if ($continuationToken) {
            $searchUrl .= "&continuation=" . $continuationToken;
        }
        $html = file_get_contents($searchUrl);

        if ($html === false) {
            return "Error fetching search results.";
        }

        $pattern = '/"videoRenderer":{"videoId":"(.*?)","thumbnail":{"thumbnails":\[{"url":"(.*?)","width":.*?"title":{"runs":\[{"text":"(.*?)"\}\].*?"longBylineText":{"runs":\[{"text":"(.*?)","navigationEndpoint".*?"publishedTimeText":{"simpleText":"(.*?)"}.*?("viewCountText":{"simpleText":"(.*?)"}|"badges").*?"lengthText":{"accessibility":{"accessibilityData":{"label":"(.*?)"}},"simpleText":"(.*?)"}/s';
        preg_match_all($pattern, $html, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $duration = $match[9];
            $durationParts = explode(':', $duration);
            $minutes = count($durationParts) > 2 
                ? intval($durationParts[0]) * 60 + intval($durationParts[1])
                : intval($durationParts[0]);

            $results[] = [
                'id' => $match[1],
                'thumbnail' => $match[2],
                'title' => html_entity_decode($match[3], ENT_QUOTES),
                'channel' => $match[4],
                'published' => $match[5],
                'views' => isset($match[7]) ? $match[7] : 'N/A',
                'duration' => $duration,
                'minutes' => $minutes,
                'highlight' => ($minutes >= $minDuration && $minutes <= $maxDuration)
            ];

            if (count(array_filter($results, function($v) { return $v['highlight']; })) >= $targetResults) {
                break 2;  // Break out of both foreach and while loops
            }
        }

        // Extract continuation token for next page
        if (preg_match('/"continuationCommand":{"token":"(.*?)"/', $html, $tokenMatch)) {
            $continuationToken = $tokenMatch[1];
        } else {
            break;  // No more results to fetch
        }
    }

    return $results;
}

$query = isset($_GET['q']) ? $_GET['q'] : '';
$minDuration = isset($_GET['min']) ? intval($_GET['min']) : 60;
$maxDuration = isset($_GET['max']) ? intval($_GET['max']) : 180;
$results = youtubeSearch($query, $minDuration, $maxDuration);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YouTube Search Results</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
        .search-form { margin-bottom: 20px; }
        .video-item { margin-bottom: 20px; padding: 10px; }
        .video-item img { max-width: 120px; vertical-align: top; margin-right: 10px; }
        .video-info { display: inline-block; }
        .highlighted { background-color: #ffffd0; }
    </style>
</head>
<body>
    <form class="search-form" method="get">
        <input type="text" name="q" value="<?php echo htmlspecialchars($query); ?>" placeholder="Enter search query">
        <label>
            Min duration (minutes):
            <input type="number" name="min" value="<?php echo $minDuration; ?>" min="0">
        </label>
        <label>
            Max duration (minutes):
            <input type="number" name="max" value="<?php echo $maxDuration; ?>" min="0">
        </label>
        <input type="submit" value="Search">
    </form>

    <?php if ($query): ?>
        <h2>Search Results for "<?php echo htmlspecialchars($query); ?>"</h2>
        <?php if (is_array($results)): ?>
            <?php foreach ($results as $video): ?>
                <div class="video-item <?php echo $video['highlight'] ? 'highlighted' : ''; ?>">
                    <img src="<?php echo htmlspecialchars($video['thumbnail']); ?>" alt="Thumbnail">
                    <div class="video-info">
                        <h3><a href="https://www.youtube.com/watch?v=<?php echo htmlspecialchars($video['id']); ?>" target="_blank"><?php echo htmlspecialchars($video['title']); ?></a></h3>
                        <p>Channel: <?php echo htmlspecialchars($video['channel']); ?></p>
                        <p>Published: <?php echo htmlspecialchars($video['published']); ?></p>
                        <p>Views: <?php echo htmlspecialchars($video['views']); ?></p>
                        <p>Duration: <?php echo htmlspecialchars($video['duration']); ?> (<?php echo $video['minutes']; ?> minutes)</p>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p><?php echo $results; ?></p>
        <?php endif; ?>
    <?php endif; ?>
</body>
</html>