package com.nativephp.mobile.ui.nativerender

import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.text.BasicText
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.LocalContentColor
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.semantics.contentDescription
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.nativephp.mobile.ui.MaterialIcon

/**
 * Kinevo in-repo element renderers for the content subtree (ui-audit UI-021).
 *
 * The vendor `native-ui` plugin is not installed on this Linux pipeline, so
 * `registerPluginRenderers()` is a no-op and only chrome rendered. These
 * registrations cover every element type our blades emit; unknown container
 * types keep falling back to `DefaultContainerNode` (NodeView).
 *
 * Uses only props that already flow through the v4 wire format
 * (NativeUINode.kt PropKey table) — no ABI changes.
 *
 * This file is the SOURCE OF TRUTH: the Android build pipeline regenerates the
 * nativephp/android template on every run, and
 * infrastructure/nativephp/linux-build/build-android-apk.sh copies this file
 * back in and patches MainActivity.kt to call registerKinevoRenderers().
 */
fun registerKinevoRenderers() {
    NativeRendererRegistry.register("text") { node, modifier ->
        TextRenderer(node, modifier)
    }
    NativeRendererRegistry.register("icon") { node, modifier ->
        IconRenderer(node, modifier)
    }
    NativeRendererRegistry.register("scroll_view") { node, modifier ->
        ScrollViewRenderer(node, modifier)
    }
    NativeRendererRegistry.register("pressable") { node, modifier ->
        PressableRenderer(node, modifier)
    }
    NativeRendererRegistry.register("text_input") { node, modifier ->
        TextInputRenderer(node, modifier)
    }
    NativeRendererRegistry.register("divider") { _, modifier ->
        HorizontalDivider(modifier = modifier)
    }
    NativeRendererRegistry.register("spacer") { _, modifier ->
        androidx.compose.foundation.layout.Spacer(modifier = modifier)
    }
}

@Composable
private fun TextRenderer(node: NativeUINode, modifier: Modifier) {
    val color = if (node.props.has("text_color")) {
        Color(node.props.getColor("text_color"))
    } else if (node.props.has("color")) {
        Color(node.props.getColor("color"))
    } else {
        LocalContentColor.current
    }
    val sizeSp = node.props.getFloat("font_size", 14f).coerceAtLeast(1f)
    val weight = when (node.props.getFloat("font_weight", 400f).toInt()) {
        in 600..1000 -> FontWeight.Bold
        in 500..599 -> FontWeight.Medium
        else -> FontWeight.Normal
    }
    val align = when (node.props.getString("text_align", "")) {
        "center" -> TextAlign.Center
        "right", "end" -> TextAlign.End
        "justify" -> TextAlign.Justify
        else -> TextAlign.Start
    }
    val maxLines = node.props.getInt("max_lines", 0).takeIf { it > 0 }
    val label = node.props.getString("a11y_label")
    val textModifier = if (label.isNotEmpty()) modifier.semantics { contentDescription = label } else modifier
    BasicText(
        text = node.props.getString("text"),
        modifier = textModifier,
        style = TextStyle(
            color = color,
            fontSize = sizeSp.sp,
            fontWeight = weight,
            textAlign = align,
        ),
        maxLines = maxLines ?: Int.MAX_VALUE,
    )
}

@Composable
private fun PressableRenderer(node: NativeUINode, modifier: Modifier) {
    // a11y_label/a11y_hint flow as string props (PropKey fallback path). The
    // clickable gesture already lives on `modifier` (NodeView.nodeGestures);
    // surface the label into the accessibility node here so TalkBack and
    // uiautomator can discover real tap targets (P27-013).
    val label = node.props.getString("a11y_label")
    val hint = node.props.getString("a11y_hint")
    val described = if (label.isNotEmpty() || hint.isNotEmpty()) {
        modifier.semantics {
            if (label.isNotEmpty()) contentDescription = label
        }
    } else {
        modifier
    }
    DefaultContainerNode(node, described)
}

@Composable
private fun IconRenderer(node: NativeUINode, modifier: Modifier) {
    val name = node.props.getString("name", node.props.getString("icon", ""))
    if (name.isEmpty()) return
    val sizeDp = node.props.getFloat("size", 24f).dp
    val tint = if (node.props.has("color")) Color(node.props.getColor("color")) else LocalContentColor.current
    MaterialIcon(
        name = name,
        contentDescription = name,
        modifier = modifier,
        size = sizeDp,
        tint = tint,
    )
}

@Composable
private fun ScrollViewRenderer(node: NativeUINode, modifier: Modifier) {
    androidx.compose.foundation.layout.Column(
        modifier = modifier
            .fillMaxWidth()
            .verticalScroll(rememberScrollState())
    ) {
        node.children.forEach { child -> NodeView(node = child) }
    }
}

@Composable
private fun TextInputRenderer(node: NativeUINode, modifier: Modifier) {
    var value by remember(node.id) { mutableStateOf(node.props.getString("value")) }
    val secure = node.props.getBool("secure")
    val keyboardType = when (node.props.getString("keyboard", node.props.getString("keyboard_type", ""))) {
        "number", "numeric", "decimal" -> KeyboardType.Number
        "email" -> KeyboardType.Email
        "phone" -> KeyboardType.Phone
        else -> KeyboardType.Text
    }
    OutlinedTextField(
        value = value,
        onValueChange = { next ->
            value = next
            val cb = node.props.getCallbackId("on_change")
            if (cb != 0) {
                NativeElementBridge.sendTextChangeEvent(cb, node.id, next)
            }
        },
        modifier = modifier.fillMaxWidth(),
        enabled = !node.props.getBool("disabled"),
        placeholder = {
            val ph = node.props.getString("placeholder")
            if (ph.isNotEmpty()) BasicText(
                text = ph,
                style = TextStyle(color = MaterialTheme.colorScheme.onSurfaceVariant),
            )
        },
        visualTransformation = if (secure) PasswordVisualTransformation() else VisualTransformation.None,
        keyboardOptions = KeyboardOptions(keyboardType = keyboardType),
        singleLine = !node.props.getBool("multiline"),
        textStyle = MaterialTheme.typography.bodyLarge,
    )
}
